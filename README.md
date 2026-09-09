# Sekarya API

REST API marketplace pekerjaan acak perseorangan: satu pihak memposting pekerjaan dengan
rentang budget, pihak lain mengajukan penawaran dengan harga sendiri, pemberi kerja
memilih, dana ditahan, pekerjaan dikerjakan, dana dilepas, keduanya saling menilai.

Satu orang bisa berada di **kedua sisi** dan bergantian — tidak ada konsep mitra atau
badan usaha. Semua pihak perseorangan.

| | |
|---|---|
| Bahasa & framework | PHP `^8.3` · Laravel `^13.17` |
| Basis data | MySQL 8+ / InnoDB — **bukan** SQLite, lihat [Kenapa MySQL](#kenapa-mysql-bukan-sqlite) |
| Autentikasi | Laravel Sanctum `^4.0`, sepasang token |
| Test | PHPUnit `^12.5` — 705 test, 41 berkas, coverage 99,96% |
| Kontrak API | OpenAPI 3.1 di `docs/openapi.yaml` — 35 endpoint |
| Observability | Axiom (opsional, mati secara bawaan) |

Diuji pada PHP 8.5.10, Laravel 13.30.1, MySQL 26.7 (Homebrew), Composer 2.10.

---

## Daftar isi

- [Persyaratan](#persyaratan)
- [Setup](#setup)
- [Konfigurasi](#konfigurasi)
- [Menjalankan](#menjalankan)
- [Test](#test)
- [Dokumentasi API](#dokumentasi-api)
- [Arsitektur](#arsitektur)
- [Struktur direktori](#struktur-direktori)
- [Aturan domain yang tidak boleh dilanggar](#aturan-domain-yang-tidak-boleh-dilanggar)
- [Konvensi git](#konvensi-git)
- [Hal yang sering menjebak](#hal-yang-sering-menjebak)

---

## Persyaratan

| Kebutuhan | Versi | Catatan |
|---|---|---|
| PHP | `>= 8.3` | Ekstensi: `pdo_mysql`, `mbstring`, `openssl` (bawaan Laravel lainnya juga) |
| Composer | 2.x | |
| MySQL | 8.0+ | InnoDB. Perlu dukungan `FULLTEXT` dan `LEAST()/GREATEST()` |
| Node.js | 18+ | Hanya untuk aset frontend dan `redocly` (lewat `npx`) |
| pcov *atau* Xdebug | — | Hanya untuk laporan coverage. Lihat [Coverage](#coverage) |

MariaDB **belum diuji**. Pencarian memakai sintaks `MATCH ... AGAINST (... IN BOOLEAN MODE)`
InnoDB; perbedaan implementasi FULLTEXT antar-mesin bisa mengubah hasilnya.

## Setup

```bash
git clone https://github.com/sekarya01/sekarya-laravel-api.git
cd sekarya-laravel-api

composer install
cp .env.example .env
php artisan key:generate
```

Buat **dua** basis data — satu untuk pengembangan, satu untuk test. Terpisah, karena
`RefreshDatabase` menghapus isi basis data yang ditunjuknya:

```bash
mysql -u root -e "
CREATE DATABASE sekarya      CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE sekarya_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Isi kredensial basis data di `.env`, lalu:

```bash
php artisan migrate --seed     # 19 migrasi -> 23 tabel, + kategori & keahlian
npm install && npm run build   # opsional, hanya untuk aset
```

Nama basis data test ada di `phpunit.xml`, **bukan** di `.env` — lihat
[Hal yang sering menjebak](#hal-yang-sering-menjebak).

## Konfigurasi

Sebagian besar bawaan Laravel. Yang khusus proyek ini:

### Basis data

```dotenv
DB_CONNECTION=mysql
DB_DATABASE=sekarya
DB_TIMEZONE=+00:00      # JANGAN diubah tanpa mengubah app.timezone juga
```

`DB_TIMEZONE` **wajib** `+00:00`. Tanpa itu, zona waktu sesi MySQL mengikuti host
(Asia/Jakarta di sini, +7), sehingga `NOW()` di SQL dan `now()` di PHP berbeda tujuh jam —
dan setiap timestamp yang dihitung server memakai jam yang berbeda dari kode yang
menulis datanya.

### Aturan aplikasi — `config/sekarya.php`

Seluruh angka bisnis ada di satu berkas: umur token, panjang & masa berlaku kode
verifikasi, batas percobaan, dan seluruh batas laju.

```dotenv
SEKARYA_ACCESS_TTL_HOURS=8          # umur access token
SEKARYA_LONG_LIVED_TTL_DAYS=30      # umur token refresh
SEKARYA_VERIFICATION_TTL_MINUTES=15
SEKARYA_RL_LOGIN=5                  # batas laju login per menit
SEKARYA_VALIDATE_EMAIL_DNS=false    # nyalakan di produksi
```

`SEKARYA_VALIDATE_EMAIL_DNS` dimatikan di lokal supaya domain uji seperti `.test` bisa
dipakai; di produksi nyalakan agar salah ketik domain ditolak di depan.

### CORS

```dotenv
CORS_ALLOWED_ORIGINS=https://app.sekarya.id,https://admin.sekarya.id
```

**Tidak pernah `*`.** `supports_credentials` tetap `false` — API ini memakai Bearer token,
bukan cookie, dan membiarkannya `false` menutup seluruh kelas CSRF lintas-origin.

### Observability — Axiom (opsional)

Mati secara bawaan. Tidak ada satu pun data yang keluar selama `AXIOM_ENABLED=false`.

```dotenv
AXIOM_ENABLED=true
AXIOM_TOKEN=xaat-...        # dari app.axiom.co, scope satu dataset, izin ingest saja
AXIOM_DATASET=sekarya
LOG_STACK=single,axiom      # jangan hilangkan `single`
```

Sebelum menyalakannya, **buktikan penyaringan PII-nya**:

```bash
php artisan sekarya:axiom --audit   # cetak hasil penyaringan, tidak mengirim apa pun
php artisan sekarya:axiom --ping    # satu event uji
```

Panduan lengkap: [`docs/OBSERVABILITY.md`](docs/OBSERVABILITY.md).

> Satu blok `AXIOM_*` saja di `.env`. Dotenv memakai kemunculan **terakhir**, jadi
> placeholder kosong yang tertinggal di bawah akan mengosongkan token yang sudah benar —
> gejalanya "kredensial belum diisi" padahal tokennya jelas terlihat.

## Menjalankan

```bash
php artisan serve                 # http://127.0.0.1:8000
php artisan queue:work            # kalau AXIOM_DELIVERY=queue atau ada job lain
php artisan route:list --path=api
```

## Test

```bash
php artisan test                  # 705 test
php artisan test tests/Unit       # lapis cepat
composer test-report              # + coverage/html, junit, testdox
bash docs/smoke.sh                # 95 pemeriksaan HTTP sungguhan, server sendiri
./vendor/bin/pint                 # format — jalankan sebelum commit
npx --yes -p @redocly/cli redocly lint docs/openapi.yaml
```

`docs/smoke.sh` menjalankan `migrate:fresh --seed` — **seluruh data pengembangan hilang.**

Dua lapis test, keduanya diperlukan: unit menguji Action tanpa HTTP, feature menguji
perkabelannya (rute, otorisasi, validasi, bentuk respons). Selain itu ada
`docs/smoke.sh` yang memanggil API sungguhan lewat HTTP — beberapa bug di riwayat proyek
ini hanya muncul di situ.

### Coverage

Butuh pcov atau Xdebug. `composer test-report` memuat pcov lewat jalur absolut yang
mungkin berbeda di mesin Anda:

```json
"coverage": "@php -d extension=/opt/homebrew/Cellar/php/8.5.10/pecl/.../pcov.so ..."
```

Sesuaikan jalurnya di `composer.json`, atau jalankan PHPUnit langsung dengan Xdebug.

## Dokumentasi API

| Berkas | Isi |
|---|---|
| [`docs/openapi.yaml`](docs/openapi.yaml) | **Kontrak.** OpenAPI 3.1, ditulis tangan |
| [`docs/API.md`](docs/API.md) | Panduan manusia — alur lengkap dengan `curl` yang bisa disalin |
| [`docs/OBSERVABILITY.md`](docs/OBSERVABILITY.md) | Logging, redaksi PII, kueri Axiom |
| [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) | Pemasangan di shared hosting, dari kelayakan sampai verifikasi |
| [`CONTRIBUTING.md`](CONTRIBUTING.md) | Konvensi commit |
| [`CLAUDE.md`](CLAUDE.md) | Keputusan arsitektur yang tidak boleh "dirapikan" |

Di luar produksi, spec juga dilayani aplikasi:

```
GET /docs               referensi ter-render
GET /docs/openapi.yaml  spec mentah
```

`docs/api.html` adalah berkas **hasil generate** dan tidak dilacak git, jadi setelah
clone baru ia belum ada — `GET /docs` baru bekerja setelah dibangun sekali:

```bash
npx --yes -p @redocly/cli redocly build-docs docs/openapi.yaml -o docs/api.html
```

Jalankan ulang perintah yang sama setiap kali `docs/openapi.yaml` berubah.

> Spesifikasi di proyek ini adalah **kontrak, bukan produk sampingan** dari anotasi.
> Menambah endpoint tanpa mencatatnya membuat suite gagal —
> `tests/Feature/Docs/ApiDocumentationTest.php` memeriksanya dua arah.

## Arsitektur

Action-Based (Clean Architecture / DDD Lite). Logika bisnis hidup **hanya** di kelas
Action — tidak di Controller, tidak di Model, tidak di Request, tidak di Resource.

```
HTTP Request
  -> FormRequest      validasi bentuk saja, tanpa logika
  -> DTO              payload readonly bertipe, sudah disanitasi
  -> Action           SELURUH logika bisnis, satu method publik: handle()
  -> Model            persistensi saja: relasi, cast, scope
  -> API Resource     pembentukan keluaran -> JSON
HTTP Response
```

Konsekuensi yang membuatnya bertahan: Action bebas HTTP, jadi bisa diuji tanpa membuat
request. Kalau sebuah Action sulit diuji tanpa HTTP, yang salah Action-nya.

Aturan lain yang dipegang: paginasi **cursor** saja (`paginate()` dilarang), uang sebagai
integer dalam satuan terkecil, kolom pembawa hak akses tidak pernah mass-assignable dan
default-nya keadaan paling tidak berhak.

## Struktur direktori

```
app/
  Actions/          29 kelas — seluruh logika bisnis
  Data/             DTO readonly
  Enums/            SSOT status & peran
  Exceptions/Domain/ pelanggaran aturan bisnis; bootstrap/app.php memetakannya ke status HTTP
  Http/
    Controllers/    35 controller invokable, masing-masing tiga pernyataan
    Requests/       validasi bentuk
    Resources/      batas pengungkapan data
    Middleware/     pencatat request Axiom
  Logging/Axiom/    redaksi PII + transport
  Models/           11 model
  Policies/         otorisasi per objek
  Support/          TokenIssuer, TaskSearch, SearchTerms, TaskHiring, TaskStatusRecorder
database/
  migrations/       19 migrasi
  factories/ seeders/
docs/               kontrak API, panduan, smoke test
tests/
  Unit/             Action, Support, Model, Enum, DTO
  Feature/          perkabelan lewat HTTP
```

## Aturan domain yang tidak boleh dilanggar

Ringkas; alasan lengkapnya di [`CLAUDE.md`](CLAUDE.md).

**Pendaftaran tidak pernah mengaktifkan akun.** `POST /auth/register` mengembalikan `202`
tanpa token. Akun berada di `pending_verification` sampai kode 6 angka dari email
diterima — itu satu-satunya jalan menuju `active` dan sepasang token.

**Dua token dengan peran berbeda.** `access` hidup 8 jam dan satu-satunya yang boleh
memanggil endpoint aplikasi. `long_lived` hidup 30 hari dan **hanya** bisa menukar diri
jadi access baru. Tanpa pemisahan ini, satu token yang bocor berumur sebulan.

**Satu task bisa merekrut banyak pekerja.** `workers_needed` menentukan berapa orang yang
diterima — bukan batas pelamar. Lelangnya tetap terbuka, dan pemberi kerja memilih
berdasarkan harga penawaran. Status task mengikuti **agregat** seluruh pekerja: dana
dilepas hanya ketika pekerja terakhir disetujui.

**Tidak ada activity tanpa dana ditahan.** Ditegakkan oleh baris basis data, bukan
disiplin kode.

**Login, kirim ulang kode, dan verifikasi memberi jawaban identik** apakah emailnya ada
atau tidak — kalau tidak, ketiganya menjadi alat pemetaan akun.

## Deploy

Panduan lengkap untuk shared hosting cPanel: [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md).

Ringkasnya: aplikasi ini memang dirancang bisa hidup di shared hosting — seluruh driver
memakai `database`, tidak ada Redis, tidak ada proses yang harus hidup terus, dan tidak
ada setelan MySQL yang perlu diminta ke penyedia hosting.

- **PHP 8.3 adalah syarat mutlak.** Banyak paket masih memakai 8.1 sebagai bawaan tapi
  menyediakan 8.3 di MultiPHP Manager — periksa daftarnya, bukan yang sedang aktif.
- Basis data dipasang sekali lewat
  [`database/schema/sekarya-install.sql`](database/schema/sekarya-install.sql): 23 tabel
  beserta indeks dan foreign key, data acuan, dan riwayat migrasi supaya
  `php artisan migrate` tahu semuanya sudah dijalankan. Tanpa data pengguna. Tabelnya urut
  menurut ketergantungan dan tidak menghapus apa pun, jadi bisa diimpor lewat phpMyAdmin
  dan aman diulang. Dibuat ulang dengan `php artisan sekarya:build-install-sql`.
- Salin `.env.production.example` jadi `.env` di server; tiap nilainya berkomentar.
- Aplikasi harus berada **di luar** `public_html`. Kalau `https://domain/.env` bisa
  diunduh, seluruh kredensial Anda sudah bocor.

## Konvensi git

Conventional Commits. Aturan lengkap dan contohnya di [`CONTRIBUTING.md`](CONTRIBUTING.md).

```
<tipe>(<cakupan>): <ringkasan>
```

Tipe: `feat` `fix` `perf` `refactor` `test` `docs` `build` `chore`.
Cakupan menyebut lapisan atau domain (`auth`, `task`, `bid`, `payment`, `search`,
`observability`, `docs`, `db`), bukan nama berkas. Ringkasan bahasa Indonesia, kalimat
perintah, maksimal 72 karakter, dan menyebut **hasilnya** bukan mekanismenya.

Branch: `dev/<nama>` untuk pekerjaan perorangan.

Sebelum commit: `./vendor/bin/pint`, `php artisan test`, dan `redocly lint`.

## Hal yang sering menjebak

### Kenapa MySQL, bukan SQLite

Aplikasi ini memakai sintaks khusus MySQL yang tidak sah di SQLite:
`MATCH(...) AGAINST (... IN BOOLEAN MODE)` untuk pencarian, dan `LEAST()/GREATEST()` di
ekspresi jarak. Menjalankan test di SQLite bukan sekadar membuktikan lebih sedikit — test
pencarian akan **gagal**, dan yang lolos tidak membuktikan apa pun soal produksi.

### Basis data test ada di `phpunit.xml`, bukan `--env=testing`

Tanpa berkas `.env.testing`, `php artisan migrate:fresh --env=testing` jatuh kembali ke
`.env` dan **menghapus basis data pengembangan**. Konfigurasi test ada di `phpunit.xml`.

### Test FULLTEXT butuh `DatabaseTruncation`

Indeks FULLTEXT InnoDB tidak diperbarui sampai transaksi COMMIT. `RefreshDatabase`
membungkus tiap test dalam transaksi lalu me-rollback-nya, sehingga baris yang dibuat di
dalam test **tidak pernah terlihat** oleh `MATCH ... AGAINST` — setiap test pencarian
mengembalikan nol baris dan tampak seperti bug aplikasi, padahal aplikasinya benar.

### Kata pendek dan imbuhan pada pencarian

`innodb_ft_min_token_size` bawaan MySQL adalah 3, jadi "AC" tidak akan pernah terindeks
apa adanya. Aplikasi ini menanganinya sendiri di `App\Support\SearchTerms` — kata pendek
disimpan bersentinel, akar kata ikut disimpan — sehingga **tidak perlu setelan server
khusus**, dan `q=bersih` menemukan judul "Membersihkan".

### Jangan menilai rencana kueri dari tabel kecil

`EXPLAIN` pada tabel berisi beberapa baris akan menunjukkan pemindaian tabel dan terlihat
seperti masalah. Rencana kueri di proyek ini diperiksa pada 20.000 baris.

## Lisensi

Hak milik. Belum ditentukan untuk publik.
