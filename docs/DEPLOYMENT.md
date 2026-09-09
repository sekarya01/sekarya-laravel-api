# Pemasangan di shared hosting

Panduan ini untuk shared hosting cPanel — jenis yang tidak memberi akses root, tidak bisa
mengubah konfigurasi MySQL, dan tidak menyediakan proses yang berjalan terus-menerus.
Aplikasi ini memang dirancang bisa hidup di situ: seluruh driver memakai `database`, tidak
ada Redis, dan tidak ada kebutuhan mengubah setelan server.

Yang tetap harus diperiksa lebih dulu ada di [Kelayakan hosting](#kelayakan-hosting) —
satu di antaranya bisa menggagalkan seluruhnya.

---

## Kelayakan hosting

Periksa **sebelum** membeli atau mengunggah apa pun.

| Kebutuhan | Minimum | Cara memeriksa |
|---|---|---|
| PHP | **8.3** | cPanel > MultiPHP Manager, atau `php -v` lewat SSH |
| MySQL | **8.0+**, InnoDB | phpMyAdmin > tab SQL: `SELECT VERSION();` |
| Ekstensi PHP | `pdo_mysql` `mbstring` `openssl` `tokenizer` `xml` `ctype` `json` `bcmath` `fileinfo` `curl` | cPanel > Select PHP Version > Extensions |
| Cron | ada | cPanel > Cron Jobs |
| Email keluar | berfungsi | Wajib — pendaftaran tidak selesai tanpa kode verifikasi |

> **PHP di bawah 8.3 adalah penghalang mutlak.** Kode ini memakai sintaks yang tidak akan
> ter-parse di versi lama; tidak ada penyesuaian kecil yang bisa menolongnya. Banyak paket
> shared hosting masih menawarkan 8.1 sebagai bawaan tapi menyediakan 8.3 di
> MultiPHP Manager — periksa daftarnya, bukan cuma yang aktif.

**MySQL 5.7 atau MariaDB belum diuji.** Pencarian memakai indeks `FULLTEXT` InnoDB dan
filter jarak memakai `LEAST()/GREATEST()`; perbedaan implementasi bisa mengubah hasilnya
tanpa menimbulkan galat.

Satu hal yang **tidak** perlu Anda minta ke penyedia hosting: aplikasi ini tidak menuntut
`innodb_ft_min_token_size` diturunkan. Pencarian kata pendek seperti "AC" ditangani di
sisi aplikasi (lihat `App\Support\SearchTerms`), jadi setelan bawaan server sudah cukup.

---

## 1. Letak berkas

Laravel harus melayani dari `public/`, sementara shared hosting menyajikan `public_html/`.
Ada dua cara, dan yang pertama jauh lebih baik.

### Cara A — pindahkan document root (dianjurkan)

Taruh seluruh aplikasi di luar web root, lalu arahkan domainnya:

```
/home/akunanda/
├── sekarya/            <- seluruh aplikasi ada di sini
│   ├── app/
│   ├── public/         <- document root diarahkan ke sini
│   ├── vendor/
│   └── .env            <- TIDAK bisa diakses lewat web
└── public_html/        <- dibiarkan kosong
```

cPanel > **Domains** > pilih domain > **Document Root** > isi `sekarya/public`.

Kenapa ini lebih baik: `.env`, `storage/`, dan `vendor/` berada **di luar** jangkauan web
sepenuhnya. Bukan karena disembunyikan, tapi karena secara struktur tidak bisa diminta.

### Cara B — kalau document root tidak bisa diubah

Sebagian paket murah mengunci `public_html`. Pisahkan isinya:

```
/home/akunanda/
├── sekarya/            <- aplikasi TANPA folder public
└── public_html/        <- isi folder public/ dipindah ke sini
    ├── index.php       <- perlu disunting, lihat di bawah
    ├── .htaccess
    ├── favicon.ico
    └── robots.txt
```

Sunting `public_html/index.php`, tiga baris yang menyebut `__DIR__`:

```php
// baris 9
if (file_exists($maintenance = __DIR__.'/../sekarya/storage/framework/maintenance.php')) {

// baris 14
require __DIR__.'/../sekarya/vendor/autoload.php';

// baris 18
$app = require_once __DIR__.'/../sekarya/bootstrap/app.php';
```

> Dengan cara ini `.env` berada di `/home/akunanda/sekarya/.env` — masih di luar web root,
> jadi tetap aman. Yang **tidak boleh** dilakukan adalah menaruh seluruh aplikasi di dalam
> `public_html`: `.env` Anda akan bisa diunduh siapa pun yang menebak alamatnya.

---

## 2. Unggah aplikasi

### Kalau ada SSH

```bash
cd ~
git clone https://github.com/sekarya01/sekarya-laravel-api.git sekarya
cd sekarya
composer install --no-dev --optimize-autoloader
```

`--no-dev` membuang PHPUnit, Pint, dan Faker — tidak dipakai di produksi dan hanya
menambah berkas.

### Kalau tidak ada SSH

Composer dijalankan di komputer sendiri, hasilnya diunggah:

```bash
# di komputer sendiri
composer install --no-dev --optimize-autoloader
zip -r sekarya.zip . -x '.git/*' 'node_modules/*' 'tests/*' 'coverage/*' '.env'
```

Unggah `sekarya.zip` lewat cPanel > File Manager, lalu Extract. Pastikan `.env` **tidak**
ikut di dalam zip — berkas itu diisi langsung di server.

---

## 3. Basis data

1. cPanel > **MySQL Databases** > buat basis data. Namanya otomatis diberi awalan nama
   akun, mis. `akunanda_sekarya`. **Catat nama lengkapnya.**
2. Buat pengguna basis data, lalu tambahkan ke basis data itu dengan **ALL PRIVILEGES**.
3. phpMyAdmin > pilih basis datanya > tab **Import** > unggah
   [`database/schema/sekarya-install.sql`](../database/schema/sekarya-install.sql).

Lewat SSH:

```bash
mysql -u akunanda_sekarya -p akunanda_sekarya < database/schema/sekarya-install.sql
```

Berkas itu berisi 23 tabel beserta seluruh indeks dan foreign key, 9 kategori, 42
keahlian, dan 19 baris riwayat migrasi. Yang terakhir penting: tanpa itu
`php artisan migrate` akan mencoba menjalankan ulang seluruh migrasi di atas tabel yang
sudah ada. Tidak ada data pengguna di dalamnya.

> Berkas ini untuk pemasangan **pertama** saja. Pembaruan skema berikutnya memakai
> `php artisan migrate`. Dan jangan pernah menjalankan `migrate:fresh` di produksi — itu
> menghapus seluruh isi basis data tanpa bertanya.

Kalau phpMyAdmin menolak karena ukuran berkas, unggah lewat SSH, atau naikkan
`upload_max_filesize` di cPanel > Select PHP Version > Options.

---

## 4. Konfigurasi

```bash
cp .env.production.example .env
php artisan key:generate      # kalau ada SSH
```

Tanpa SSH: jalankan `php artisan key:generate --show` di komputer sendiri, salin hasilnya
ke `APP_KEY` di `.env` server.

> **`APP_KEY` tidak boleh berubah setelah ada data masuk.** Nomor dokumen dan nomor
> rekening disimpan terenkripsi dengan kunci itu; menggantinya membuat data tersebut
> tidak bisa dibaca lagi, dan tidak ada cara memulihkannya.

Yang wajib diisi: `APP_URL`, kredensial `DB_*`, `MAIL_*`, dan `CORS_ALLOWED_ORIGINS`.
Pastikan `APP_ENV=production` dan `APP_DEBUG=false`.

Penjelasan tiap nilai ada sebagai komentar di dalam `.env.production.example`.

---

## 5. Izin berkas

Dua direktori harus bisa ditulis oleh PHP:

```bash
chmod -R 755 storage bootstrap/cache
```

Di shared hosting, PHP berjalan sebagai pengguna akun Anda sendiri, jadi `755` sudah
cukup. **Jangan `777`.** Itu memberi izin tulis kepada setiap pengguna lain di server yang
sama — dan di shared hosting, pengguna lain itu benar-benar ada.

Kalau aplikasi menyimpan berkas unggahan yang harus bisa diakses publik:

```bash
php artisan storage:link
```

Tanpa SSH, buat symlink lewat File Manager, atau lewati langkah ini — verifikasi identitas
di aplikasi ini menyimpan **path** pada disk privat, bukan URL publik, jadi ia tidak
membutuhkannya.

---

## 6. Optimasi produksi

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

> **Jalankan di SERVER, bukan di laptop.** Rute `/docs` dan `/docs/openapi.yaml`
> didaftarkan hanya ketika `APP_ENV` bukan `production`. Kalau cache rute dibuat di
> komputer sendiri (yang `APP_ENV=local`) lalu diunggah, kedua rute itu ikut terbawa —
> dan spesifikasi lengkap API Anda, termasuk seluruh endpoint dan kode galatnya, terbuka
> untuk umum.

Tanpa SSH, jalankan lewat cron sekali (`Cron Jobs` > jadwalkan, lalu hapus setelah
berhasil), atau lewat Terminal di cPanel bila tersedia.

**Setiap kali `.env` berubah, cache-nya harus dibuat ulang:**

```bash
php artisan optimize:clear && php artisan config:cache && php artisan route:cache
```

Nilai `.env` yang baru **tidak akan terbaca** selama cache lama masih ada. Ini penyebab
paling umum dari "sudah saya ubah tapi tidak ngefek".

---

## 7. Cron

cPanel > **Cron Jobs**. Sesuaikan path PHP-nya — cPanel biasanya menyediakan versi khusus
seperti `/opt/cpanel/ea-php83/root/usr/bin/php`.

**Penjadwal Laravel**, setiap menit:

```
* * * * * cd /home/akunanda/sekarya && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
```

**Pekerja antrean.** Shared hosting tidak mengizinkan proses yang hidup terus, jadi jangan
memakai `queue:work` biasa — ia tidak akan pernah berhenti dan akan dimatikan penyedia.
Pakai bentuk yang selesai sendiri:

```
* * * * * cd /home/akunanda/sekarya && /usr/local/bin/php artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```

`--max-time=55` membuatnya berhenti sebelum cron menit berikutnya menyala, sehingga tidak
pernah ada dua pekerja berebut antrean yang sama.

Saat ini belum ada tugas terjadwal maupun job yang wajib. Antrean baru terpakai kalau
`AXIOM_DELIVERY=queue`; untuk shared hosting biarkan `sync`.

---

## 8. Verifikasi setelah pasang

Jalankan dari komputer sendiri, ganti domainnya:

```bash
BASE=https://api.domainanda.id

# 1. Aplikasi hidup
curl -s -o /dev/null -w '%{http_code}\n' "$BASE/up"                    # 200

# 2. API menjawab, dan menolak tanpa token
curl -s "$BASE/api/v1/categories" -H 'Accept: application/json' \
  -w '\n%{http_code}\n'                                                # 401

# 3. Dokumentasi TIDAK terbuka di produksi
curl -s -o /dev/null -w '%{http_code}\n' "$BASE/docs"                  # 404

# 4. .env tidak bisa diunduh
curl -s -o /dev/null -w '%{http_code}\n' "$BASE/.env"                  # 403 atau 404

# 5. Jejak galat tidak bocor (APP_DEBUG=false)
#    Halaman debug Laravel menampilkan kueri, path server, dan potongan kode.
#    Yang benar: 404 polos. Yang salah: halaman berisi "Whoops" atau jejak stack.
curl -s "$BASE/rute-yang-tidak-ada" | head -c 300

# 6. Alur pendaftaran — email harus benar-benar sampai
curl -s -X POST "$BASE/api/v1/auth/register" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"name":"Uji Coba","email":"anda@domainanda.id","phone":"+628111000111",
       "password":"RahasiaKuat2026","password_confirmation":"RahasiaKuat2026"}' \
  -w '\n%{http_code}\n'                                                # 202
```

Nomor 3 dan 4 adalah pemeriksaan keamanan, bukan formalitas. Kalau `$BASE/.env`
mengembalikan `200`, aplikasi ditaruh **di dalam** `public_html` — hentikan, pindahkan
sesuai [Letak berkas](#1-letak-berkas), lalu **ganti seluruh kredensial**: `APP_KEY`,
kata sandi basis data, kata sandi email, dan token pihak ketiga. Anggap semuanya sudah
bocor.

Nomor 6 belum selesai sampai emailnya benar-benar masuk. Kalau tidak, tidak ada satu pun
akun yang bisa diaktifkan, dan seluruh API praktis tidak bisa dipakai.

---

## 9. Pembaruan berikutnya

```bash
cd ~/sekarya
php artisan down                   # halaman pemeliharaan
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force        # --force wajib di produksi; ia tidak bertanya
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan up
```

Tanpa SSH, unggah ulang berkasnya lalu jalankan `migrate --force` dan perintah cache lewat
cron sekali jalan.

Backup basis data **sebelum** `migrate` — cPanel > phpMyAdmin > Export, atau:

```bash
mysqldump -u PENGGUNA -p NAMA_DB > backup-$(date +%F).sql
```

---

## Membuat ulang berkas pemasangan basis data

Wajib setiap kali ada **migrasi baru**. Kalau tidak, pemasangan berikutnya kehilangan tabel
— dan `php artisan migrate` mengira pekerjaannya sudah selesai, karena riwayat migrasinya
ikut di berkas itu.

```bash
php artisan migrate:fresh --seed          # basis data bersih, seperti hosting baru
php artisan sekarya:build-install-sql     # tulis ulang berkasnya
```

Lalu **buktikan impornya** — berkas yang tidak pernah diuji impor tidak layak dikirim ke
orang yang tidak punya SSH:

```bash
php artisan db:wipe --force
mysql -u root sekarya < database/schema/sekarya-install.sql   # pertama
mysql -u root sekarya < database/schema/sekarya-install.sql   # kedua, harus tetap berhasil
php artisan migrate --pretend --force                         # harus: "Nothing to migrate"
php artisan test tests/Feature/Deployment
```

### Kenapa perintah, bukan `mysqldump`

`mysqldump` menghasilkan berkas yang **gagal diimpor lewat phpMyAdmin**, dan gagalnya tidak
menjelaskan apa-apa: `CREATE TABLE` pertama berhenti tanpa keterangan. Sebabnya ia
mengurutkan tabel secara **alfabetis**, sehingga `activities` dibuat lebih dulu daripada
`tasks`, `users`, dan `payments` yang dirujuk foreign key-nya. Dump itu hanya selamat
karena `FOREIGN_KEY_CHECKS=0` — dan mysqldump menaruh baris itu di dalam komentar
bersyarat versi (`/*!40014 ... */`), yang boleh dilewati klien mana pun yang mengurai
berkas SQL sendiri.

Perintah ini mengurutkan tabel menurut **ketergantungan**, jadi setiap foreign key menunjuk
tabel yang sudah dibuat di atasnya — impornya berhasil bahkan kalau pemeriksaan foreign key
tidak pernah dimatikan sama sekali.

Diuji dengan cara yang paling keras yang bisa dilakukan: seluruh pernyataan dijalankan satu
per satu, **masing-masing di koneksi baru**, dengan setiap `SET` sesi dibuang. 93 dari 93
berhasil.

Lima hal yang membuat berkas semacam ini gagal di shared hosting, dan semuanya sudah
dihindari:

| Penyebab | Kenapa gagal di sana |
|---|---|
| Tabel urut abjad | Foreign key menunjuk tabel yang belum dibuat |
| `FOREIGN_KEY_CHECKS` di dalam `/*! */` | Boleh dilewati klien; phpMyAdmin melewatinya |
| `DROP TABLE` | Sebagian hosting menahan hak DROP. Juga menghancurkan data kalau salah basis data. |
| `CREATE DATABASE` | Nama basis data ditentukan panel, berawalan nama akun |
| `CREATE TABLE` tanpa `IF NOT EXISTS` | Impor ulang setelah gagal separuh langsung berhenti |

`tests/Feature/Deployment/InstallSchemaTest.php` menjaga kelimanya, plus memastikan berkas
ini tidak pernah memuat data selain kategori, keahlian, dan riwayat migrasi — ia ada di
repositori publik.

---

## Masalah yang sering muncul

| Gejala | Sebab dan penanganan |
|---|---|
| `500` tanpa keterangan | Baca `storage/logs/laravel-*.log`. Paling sering: `storage/` tidak bisa ditulis, atau `APP_KEY` kosong. |
| Perubahan `.env` tidak berpengaruh | Cache konfigurasi masih yang lama. `php artisan optimize:clear` lalu buat ulang. |
| `SQLSTATE[HY000] [1045]` | Kredensial basis data salah, atau pengguna belum ditambahkan ke basis datanya di cPanel. |
| Email tidak terkirim | Sebagian besar shared hosting memblokir port 25. Pakai 465 (`smtps`) atau 587 (`tls`). |
| `419` atau sesi aneh | Tidak berlaku untuk API ini — ia memakai Bearer token, bukan cookie. Kalau muncul, permintaannya salah alamat. |
| `#1046 - No database selected` saat Import | Import dijalankan dari halaman utama phpMyAdmin. **Klik nama basis datanya di panel kiri lebih dulu**, sampai judul halaman berbunyi "Database: ...", baru buka tab Import. Berkasnya sengaja tidak memilih basis data sendiri karena namanya berbeda di tiap akun. |
| `#1142 - command denied` | Pengguna basis data belum ditambahkan ke basis datanya, atau tanpa ALL PRIVILEGES. cPanel > MySQL Databases > Add User To Database. |
| Impor berhenti di tengah | Ulangi saja — berkasnya aman dijalankan ulang. Kalau berhenti lagi di titik yang sama, naikkan `max_execution_time` di cPanel > Select PHP Version > Options, atau impor lewat SSH. |
| Pencarian tidak menemukan apa pun | Tabel `task_search` kosong. Terisi otomatis saat task dibuat; untuk data lama, jalankan ulang impor atau perbarui judulnya. |
| `/docs` terbuka di produksi | Cache rute dibuat saat `APP_ENV` bukan `production`. Ulangi di server. |
| Batas laju terlalu cepat kena | Shared hosting sering berbagi IP keluar. Naikkan `SEKARYA_RL_*`, tapi jangan `SEKARYA_RL_LOGIN` — di situlah tebakan kata sandi terjadi. |
