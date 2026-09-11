# Sekarya API — Panduan

Semua yang ada di dokumen ini dijalankan terhadap kode ini, bukan disusun dari ingatan.

| | |
|---|---|
| **Base URL** | `http://127.0.0.1:8000/api/v1` |
| **Kontrak mesin** | [`docs/openapi.yaml`](openapi.yaml) — OpenAPI 3.1, lint bersih, 57 operation cocok dengan 57 rute nyata |
| **Uji otomatis** | `bash docs/smoke.sh` — 162 pemeriksaan |
| **Database** | MySQL 8+ / InnoDB |
| **Wajib di setiap request** | `Accept: application/json` — tanpa ini Laravel bisa membalas HTML |

Kalau dokumen ini dan spec berselisih, **spec plus `php artisan test` yang benar.**

---

## Cara tercepat memastikan semuanya jalan

```bash
bash docs/smoke.sh
```

Menjalankan server sendiri, mereset database, mendaftar akun lewat alur auth yang
sebenarnya, menjalankan 162 pemeriksaan, lalu membereskan diri. Keluaran akhir:

```
SEMUA LULUS  132/162 pemeriksaan
```

Kalau mau memakai server yang sudah jalan: `bash docs/smoke.sh 8000`.

> [!warning] Smoke test menjalankan `migrate:fresh --seed` — seluruh data dev hilang.

Sisa dokumen ini untuk mencoba manual.

---

## 0. Persiapan

```bash
# sekali saja
mysql -u root -e "CREATE DATABASE IF NOT EXISTS sekarya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate:fresh --seed     # 26 tabel + 9 kategori + 42 keahlian
php artisan serve                    # http://127.0.0.1:8000

export BASE=http://127.0.0.1:8000/api/v1
```

Kode verifikasi dikirim lewat email. Dengan `MAIL_MAILER=log` (bawaan `.env` dev),
kodenya masuk ke `storage/logs/laravel.log` sehingga bisa dibaca tanpa mailbox nyata.

---

## 1. Daftar — akun **tidak** langsung aktif

```bash
curl -s -X POST "$BASE/auth/register" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{
    "first_name":"Budi",
    "last_name":"Prasetyo",
    "username":"budi.prasetyo",
    "email":"budi@sekarya.test",
    "phone":"+628111222333",
    "password":"RahasiaKuat2026",
    "password_confirmation":"RahasiaKuat2026",
    "city":"Jakarta"
  }' -w '\nstatus=%{http_code}\n' | python3 -m json.tool
```

`202 Accepted`:

```json
{
  "message": "Akun dibuat. Kode verifikasi dikirim ke email Anda.",
  "data": {
    "email": "budi@sekarya.test",
    "status": "pending_verification",
    "code_expires_in_minutes": 15,
    "next_step": "POST /api/v1/auth/verify-email"
  }
}
```

**Yang perlu diperhatikan**

- **Tidak ada token di respons ini.** Itu inti langkahnya — mengembalikan token di sini
  akan membuat verifikasi email jadi tanpa arti.
- `status` = `pending_verification`. Akun belum bisa apa-apa.
- Wajib: `first_name`, `email`, `password` (+`password_confirmation`).
  Opsional: `last_name`, `username` (huruf/angka/titik/garis bawah, unik),
  `phone` (boleh kosong; format `+628…`, unik bila diisi), `city`, `province`.
- `name` lama masih diterima sebagai alias (dipecah jadi depan/belakang) agar
  klien lama tidak putus — klien baru wajib kirim `first_name`.
- Kolom `name` di database disinkron dari depan+belakang, jadi seluruh
  pembaca lama (profil, notifikasi, admin) tidak berubah.
- Kata sandi diperiksa terhadap **basis data kebocoran publik**; kata sandi yang pernah
  bocor ditolak `422` walaupun panjangnya cukup.

Coba login sekarang — ditolak:

```bash
curl -s -X POST "$BASE/auth/login" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"budi@sekarya.test","password":"RahasiaKuat2026"}' \
  -w '\nstatus=%{http_code}\n'
```

```json
{ "message": "Email belum diverifikasi. Masukkan kode yang dikirim ke email Anda.",
  "code": "email_not_verified" }
```

---

## 2. Verifikasi — di sini token diterbitkan

Ambil kodenya dari log:

```bash
export CODE=$(grep -oE '\*\*[0-9]{6}\*\*' storage/logs/laravel.log | tail -1 | tr -d '*')
echo "kode: $CODE"
```

Coba kode salah dulu, supaya terlihat batasnya bekerja:

```bash
curl -s -X POST "$BASE/auth/verify-email" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"budi@sekarya.test","code":"000000"}' -w '\nstatus=%{http_code}\n'
```

```json
{ "message": "Kode verifikasi salah.",
  "code": "invalid_verification_code",
  "context": { "attempts_left": 4 } }
```

Ulangi dan `attempts_left` benar-benar **turun** — 4, 3, 2, 1, 0. Setelah habis, kode itu
mati **walaupun angkanya benar**, dan harus minta kode baru.

Ada **dua** batas yang berbeda cara gagalnya, dan itu disengaja:

| Batas | Di mana | Bisa dihindari dengan |
|---|---|---|
| 6 permintaan/menit per email+IP | middleware `throttle` | rotasi IP |
| 5 percobaan per kode | baris di database | **tidak bisa** |

Sekarang kode yang benar:

```bash
curl -s -X POST "$BASE/auth/verify-email" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d "{\"email\":\"budi@sekarya.test\",\"code\":\"$CODE\"}" | python3 -m json.tool
```

```json
{
  "data": {
    "token_type": "Bearer",
    "access_token": "11|aaaa…",
    "long_lived_token": "10|bbbb…",
    "access_expires_at": "2026-09-08T17:54:55+00:00",
    "long_lived_expires_at": "2026-10-08T09:54:55+00:00",
    "access_expires_in_seconds": 28799,
    "user": { "status": "active", "…": "…" }
  }
}
```

**Simpan keduanya.** Nilai token hanya bisa dibaca sekali — yang tersimpan di database
cuma hash-nya.

```bash
export AT=<access_token>
export LT=<long_lived_token>
```

`28799` detik ≈ **8 jam**, sesuai ketentuan.

---

## 3. Dua jenis token, dua peran

| Token | Umur | Boleh dipakai untuk |
|---|---|---|
| `access_token` | **8 jam** | Semua endpoint aplikasi |
| `long_lived_token` | 30 hari | **Hanya** `POST /auth/refresh` |

Buktikan pemisahannya — keempat perintah ini menunjukkan seluruh aturannya:

```bash
# access token boleh
curl -s -o /dev/null -w 'access -> /me            : %{http_code}\n' \
  "$BASE/me" -H "Authorization: Bearer $AT" -H 'Accept: application/json'

# long_lived DITOLAK di endpoint aplikasi
curl -s -o /dev/null -w 'long_lived -> /me        : %{http_code}\n' \
  "$BASE/me" -H "Authorization: Bearer $LT" -H 'Accept: application/json'

# access token DITOLAK untuk refresh
curl -s -o /dev/null -w 'access -> /auth/refresh  : %{http_code}\n' \
  -X POST "$BASE/auth/refresh" -H "Authorization: Bearer $AT" -H 'Accept: application/json'

# long_lived boleh refresh
curl -s -o /dev/null -w 'long_lived -> refresh    : %{http_code}\n' \
  -X POST "$BASE/auth/refresh" -H "Authorization: Bearer $LT" -H 'Accept: application/json'
```

```
access -> /me            : 200
long_lived -> /me        : 403
access -> /auth/refresh  : 403
long_lived -> refresh    : 200
```

Lapisan kedua itu bukan hiasan: `auth:sanctum` hanya membuktikan *"token ini sah"*, bukan
*"token ini boleh memanggil endpoint aplikasi"*. Tanpa pengecekan jenis token,
`long_lived_token` yang berumur 30 hari bisa memakai seluruh API.

### Rotasi: token lama mati

```bash
# minta access token baru
export AT_BARU=$(curl -s -X POST "$BASE/auth/refresh" \
  -H "Authorization: Bearer $LT" -H 'Accept: application/json' \
  | python3 -c 'import json,sys;print(json.load(sys.stdin)["data"]["access_token"])')

curl -s -o /dev/null -w 'access LAMA : %{http_code}\n' "$BASE/me" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json'
curl -s -o /dev/null -w 'access BARU : %{http_code}\n' "$BASE/me" \
  -H "Authorization: Bearer $AT_BARU" -H 'Accept: application/json'
```

```
access LAMA : 401
access BARU : 200
```

`long_lived_token` **tidak** berubah — klien tetap memakai yang sudah dipegang.

### Saat access token kedaluwarsa

Balasannya `401` dengan kode mesin khusus, supaya klien tahu harus refresh tanpa menebak
dari pesan:

```json
{ "message": "Token tidak valid atau sudah kedaluwarsa.", "code": "unauthenticated" }
```

Alur klien: `401 unauthenticated` → `POST /auth/refresh` dengan `long_lived_token` →
ulangi request. Kalau refresh juga `401`, `long_lived_token` habis atau dicabut — pengguna
harus login ulang.

### Logout mencabut **semuanya**

```bash
curl -s -X POST "$BASE/auth/logout" -H "Authorization: Bearer $AT_BARU" -H 'Accept: application/json'
```

Termasuk `long_lived_token`. Mencabut access token saja akan menyisakan kredensial yang
masih bisa menerbitkan akses baru — itu bukan logout.

---

## 4. Katalog — dibaca sebelum menentukan budget

```bash
curl -s "$BASE/categories" -H "Authorization: Bearer $AT" -H 'Accept: application/json' \
  | python3 -c '
import json,sys
for c in json.load(sys.stdin)["data"][:3]:
    r = c["reference_price"]
    print(f"{c[\"slug\"]:16} median Rp{r[\"median\"] or 0:,}  sample={r[\"sample_size\"]}  dari_data_nyata={r[\"from_real_data\"]}")
'
```

```
mencuci          median Rp80,000   sample=0  dari_data_nyata=False
bersih-rumah     median Rp150,000  sample=0  dari_data_nyata=False
jaga-hewan       median Rp100,000  sample=0  dari_data_nyata=False
```

> [!important] `from_real_data` wajib dipakai UI
> Saat peluncuran belum ada task selesai untuk dihitung, jadi angka di atas masih
> **perkiraan manual**. Menampilkannya seolah data nyata akan menyesatkan pemberi kerja
> sejak hari pertama. Bedakan "perkiraan kami" dari "dari 240 pekerjaan serupa".
>
> Angkanya dihitung dari `agreed_amount` task berstatus `completed`, memakai **median** —
> bukan rata-rata, agar satu task mahal tidak merusaknya.

Keahlian lebih rinci daripada kategori:

```bash
curl -s "$BASE/skills?category_id=2" -H "Authorization: Bearer $AT" -H 'Accept: application/json'
```

---

## 5. Buat task — `budget_max` opsional, `workers_needed` menentukan skala

Satu task bisa merekrut **banyak orang**. `workers_needed` (default `1`) adalah berapa
orang yang akan **diterima**.

**Ini bukan batas jumlah pelamar.** Lelangnya tetap terbuka untuk siapa pun — task 30
orang boleh menerima 200 penawaran — dan pemberi kerja memilih 30 pemenang berdasarkan
harga yang mereka ajukan. Mengisi slot ke-30 menutup lelang, dan pelamar yang masih
menunggu otomatis `rejected`.


```bash
curl -s -X POST "$BASE/tasks" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{
    "category_id": 2,
    "title": "Cuci AC 2 unit di rumah",
    "description": "Servis AC split, freon dan cuci evaporator.",
    "budget_min": 150000,
    "city": "Jakarta",
    "latitude": -6.1754,
    "longitude": 106.8272,
    "skills": ["cuci-ac"],
    "publish_now": true,
    "options": [{ "label": "Bawa alat sendiri", "value": true }]
  }' -w '\nstatus=%{http_code}\n' | python3 -m json.tool
```

`201 Created`, sebagian:

```json
{
  "data": {
    "id": "01M20DM1EK5PMNW8710QWKXDA4",
    "task_number": "TK-260908-VHUJCF",
    "status": "open",
    "budget": { "min": 150000, "max": null, "reference_median": 150000 },
    "skills": [{ "slug": "cuci-ac", "name": "Cuci ac" }],
    "bids_count": 0
  }
}
```

```bash
export TASK=01M20DM1EK5PMNW8710QWKXDA4
```

**Yang perlu diperhatikan**

- `budget.max` = **`null`**, bukan `0`, bukan hilang. Artinya *tidak ada batas atas*.
  Konsekuensi untuk klien: **jangan pernah** menulis `amount BETWEEN min AND max` —
  perbandingan apa pun harus menoleransi `max` yang `null`.
- `reference_median` **disalin** ke task saat dibuat. Harga referensi kategori berubah
  seiring data masuk; tanpa salinan ini, sengketa lama tidak bisa dinilai dengan angka
  yang berlaku waktu itu.
- URL memakai **ULID**, bukan id numerik. Id berurutan membocorkan volume bisnis.
- Tanpa `publish_now`, task tersimpan sebagai `draft` sampai
  `POST /tasks/{task}/publish`.

---

## 6. Lelang

Dari akun **lain** (daftar dan verifikasi seperti langkah 1–2, atau pakai `signup()` di
`smoke.sh`):

```bash
curl -s -X POST "$BASE/tasks/$TASK/bids" \
  -H "Authorization: Bearer $AT_PEKERJA" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"amount":220000,"message":"Bawa alat sendiri, sekitar 3 jam","estimated_hours":3}' \
  -w '\nstatus=%{http_code}\n'
```

`201`. Kirim lagi dengan nominal berbeda dan balasannya **`200`**, bukan `201` — satu
orang satu penawaran per task, jadi pengiriman kedua **mengubah** yang ada.

### Penolakan yang memang harus terjadi

```bash
# menawar task sendiri
curl -s -X POST "$BASE/tasks/$TASK/bids" -H "Authorization: Bearer $AT" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"amount":200000}' -w '\nstatus=%{http_code}\n'
```
```json
{ "message": "Tidak bisa mengajukan penawaran pada task sendiri.", "code": "cannot_bid_own_task" }
```

```bash
# di bawah budget minimum
-d '{"amount":100000}'
```
```json
{ "message": "Penawaran tidak boleh di bawah budget minimum task.",
  "code": "bid_below_minimum", "context": { "budget_min": 150000 } }
```

> [!note] `budget_max` **tidak** divalidasi
> Menawar di atas batas atas **diizinkan**. Itu keputusan produk: pemberi kerja diberi
> kebebasan penuh memilih, termasuk tawaran mahal yang menawarkan lebih. `budget_min`
> adalah batas keras; `budget_max` hanya petunjuk.

### Daftar penawaran — bahan pertimbangan

Hanya pemilik task (`403` untuk yang lain):

```bash
curl -s "$BASE/tasks/$TASK/bids?sort=rating" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' \
  | python3 -c '
import json,sys
for b in json.load(sys.stdin)["data"]:
    w = b["bidder"]
    print(f"Rp{b[\"amount\"]:>9,}  {w[\"name\"]:18} rating {w[\"as_worker\"][\"rating_avg\"]}  "
          f"selesai {w[\"as_worker\"][\"tasks_completed\"]:>3}  KTP {w[\"identity_verified\"]}")
'
```

```
Rp  220,000  Siti Penerima      rating 4.9  selesai 214  KTP True
Rp  180,000  Agus Penawar       rating 0    selesai   0  KTP False
```

`sort=amount` (default) termurah dulu, `sort=rating` reputasi tertinggi dulu, `sort=newest`
terbaru dulu.

Perhatikan rating dipisah **dua peran** (`as_worker` dan `as_poster`). Seseorang bisa jago
mengerjakan tapi buruk sebagai pemberi kerja — satu angka gabungan menyembunyikan itu dari
kedua pihak.

---

## 7. Deal → transfer → activity

```bash
export BID=<ulid penawaran terpilih>
curl -s -X POST "$BASE/bids/$BID/accept" -H "Authorization: Bearer $AT" -H 'Accept: application/json'
```

Dalam satu transaksi: penawaran itu jadi `accepted`, satu slot terisi, dan tagihan task
bertambah sebesar penawarannya.

**Deal baru terjadi saat slot TERAKHIR terisi.** Sampai saat itu task tetap `open` dan
seleksi berikutnya masih berjalan. Panggil aksi ini sekali per pekerja:

```bash
curl -s "$BASE/tasks/$TASK" -H "Authorization: Bearer $AT" -H 'Accept: application/json' \
  | python3 -c 'import json,sys; print(json.load(sys.stdin)["data"]["hiring"])'
# {'workers_needed': 3, 'workers_hired': 1, 'slots_remaining': 2}
```

Kalau pelamar tidak sebanyak yang dibutuhkan, **mulai saja dengan yang ada** — pekerjaan
bertanggal tidak boleh tersandera angka target:

```bash
curl -s -X POST "$BASE/tasks/$TASK/start" -H "Authorization: Bearer $AT" -H 'Accept: application/json'
```

`workers_needed` diturunkan ke jumlah yang sudah diterima, pelamar yang masih menunggu
ditolak, dan task masuk `dealt`. Targetnya **diturunkan**, bukan sekadar dipaksa deal —
kalau dibiarkan di angka semula, task ini akan selamanya terlihat kekurangan orang.

`agreed_amount` pada task adalah **total** untuk seluruh pekerja. Harga per orang ada di
penawaran masing-masing.

Activity **belum ada**:

```bash
curl -s "$BASE/tasks/$TASK" -H "Authorization: Bearer $AT" -H 'Accept: application/json' \
  | python3 -c 'import json,sys; print("activity:", json.load(sys.stdin)["data"].get("activities") or "BELUM ADA")'
```

Sekarang transfer. **Dua langkah, dua orang berbeda** — dan itu inti aturannya:

Path-nya masih `payment/hold` — nama lama dipertahankan supaya klien yang sudah ada
tidak perlu diubah — tapi **panggilan ini tidak lagi menahan dana.** Yang berubah untuk
klien: `data.status` sekarang `awaiting_confirmation`, bukan `held`.

```bash
# 1. Pemberi kerja: "saya sudah transfer"
curl -s -X POST "$BASE/tasks/$TASK/payment/hold" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' -w '\nstatus=%{http_code}\n' | python3 -m json.tool
```

`200`, dan **tidak ada yang terbuka**:

```json
{
  "data": {
    "id": "01M20DM2H5B7ZQ0X4K9J3P8T2R",
    "status": "awaiting_confirmation",
    "amount": 270000,
    "is_held": false,
    "awaits_confirmation": true,
    "reported_at": "2026-09-10T09:12:44+00:00",
    "rejection_reason": null
  }
}
```

Pekerjaan belum boleh dimulai, karena yang baru ada adalah **pernyataan** bahwa uangnya
dikirim. Yang menyatakan uangnya benar-benar **diterima** adalah orang yang melihat mutasi
rekening — pengelola:

```bash
# 2. Pengelola: mutasi cocok -> dana ditahan -> activity dibuka
export PAY=<ulid tagihan, dari respons di atas>
curl -s -X POST "$BASE/admin/payments/$PAY/confirm" \
  -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json' | python3 -m json.tool
```

Sesudah itu barulah activity ada — satu **per pekerja** yang diterima:

```bash
curl -s "$BASE/activities/mine" -H "Authorization: Bearer $WT" -H 'Accept: application/json'
```

```json
{
  "data": [
    {
      "id": "01M20DM2GTG81MH7QD8GAK1B1D",
      "status": "open",
      "agreed_amount": 120000,
      "opened_at": "2026-09-10T09:20:00+00:00",
      "payment": { "status": "held", "is_held": true },
      "task": { "status": "active" }
    }
  ]
}
```

`agreed_amount` di sini adalah harga **per orang**, dari penawarannya sendiri — bukan
total task. Tagihannya satu baris sebesar jumlah semuanya, karena pemberi kerja
mentransfer sekali.

Perekrutan harus **selesai** dulu (task `dealt`). Selama masih ada slot kosong, laporan
transfer ditolak — kalau tidak, pekerja yang direkrut belakangan tidak akan pernah punya
activity.

Kalau dananya tidak ditemukan, pengelola menolak laporannya dan tagihan **kembali ke
`pending`** dengan `rejection_reason` yang bisa dibaca pemberi kerja di
`GET /tasks/{task}/payment`. Setelah diperbaiki, laporannya diulang.

> [!important] Ini **stub**
> Mekanisme pembayaran belum diriset, jadi belum ada gateway. Nanti `confirm` dipicu
> webhook, bukan tangan pengelola. Yang tidak akan berubah: **activity hanya boleh terbuka
> ketika dana benar-benar ditahan**, dan yang menyatakannya bukan pihak yang membayar.
> Selama aturan itu dipegang, seluruh alur bisa dibangun dan diuji tanpa gateway sama
> sekali.

Melapor lagi setelah dana ditahan ditolak `422 invalid_status_transition`. Begitu juga
mengonfirmasi tagihan yang belum pernah dilaporkan — **tidak ada jalan dari `pending`
langsung ke `held`.**

---

## 8. Kerjakan dan selesaikan

```bash
export ACT=01M20DM2GTG81MH7QD8GAK1B1D

# penerima kerja
curl -s -X POST "$BASE/activities/$ACT/start"  -H "Authorization: Bearer $AT_PEKERJA" -H 'Accept: application/json'
curl -s -X POST "$BASE/activities/$ACT/submit" -H "Authorization: Bearer $AT_PEKERJA" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"worker_note":"Sudah beres semua","proof_photos":["proof/a.jpg","proof/b.jpg"]}'

# pemberi kerja
curl -s -X POST "$BASE/activities/$ACT/approve" -H "Authorization: Bearer $AT" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"poster_note":"Rapi, terima kasih"}' | python3 -m json.tool
```

Setelah disetujui: activity `approved` dan `tasks_completed` orang itu bertambah.

**Task selesai dan dana dilepas hanya ketika SELURUH pekerja disetujui.** Pada task
banyak orang, persetujuan pertama tidak menyelesaikan apa pun di tingkat task — kalau ia
melepas dana, seluruh tagihan keluar untuk satu orang dan pekerja lain, yang upahnya ada
di tagihan yang sama, tidak akan pernah bisa dibayar.

Aturan yang sama berlaku pada penyerahan: task baru jadi `submitted` setelah semua
pekerja menyerahkan hasilnya, bukan setelah yang paling cepat.

Kalau ditolak (`/reject`), task jadi `disputed` dan **dana tetap ditahan**. Penyelesaian
sengketa oleh admin belum tersedia di API ini.

`proof_photos` bukan pelengkap: begitu ada dana ditahan, akan ada sengketa "pekerjaannya
tidak beres", dan tanpa bukti berfoto keputusan admin cuma tebak-tebakan.

Kedua sisi hanya boleh melakukan bagiannya — penerima kerja mencoba `approve` dapat `403`.

---

## 9. Penilaian dua arah

```bash
curl -s -X POST "$BASE/tasks/$TASK/reviews" -H "Authorization: Bearer $AT" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"rating":5,"comment":"Kerja bagus dan tepat waktu"}' -w '\nstatus=%{http_code}\n'
```

Pada task **banyak pekerja**, pemberi kerja menilai satu per satu dan **harus** menyebut
siapa:

```bash
curl -s -X POST "$BASE/tasks/$TASK/reviews" -H "Authorization: Bearer $AT" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"rating":5,"worker_id":"01M21VBQXNAJVGVP6ZM4C5QS8W"}'
```

Dikosongkan pada task lebih dari satu pekerja → `422 review_target_required`. Menebak
sasarannya berarti menaruh rating pada orang yang salah, dan rating tidak bisa dicabut.
Task satu orang tetap boleh mengosongkannya — sasarannya jelas.

Penerima kerja tidak perlu mengisinya: sasarannya selalu pemberi kerja.

Perannya ditentukan otomatis dari posisi pemanggil pada task itu. Hanya setelah task
`completed`, dan **satu kali** per orang per task — pengiriman kedua `422
review_not_allowed`.

Kedua aturan itulah yang membuat rating tidak bisa dipalsukan: hanya orang yang benar-benar
bertransaksi, sekali saja.

```bash
curl -s "$BASE/users/<ulid>/reviews?role=worker" -H "Authorization: Bearer $AT" -H 'Accept: application/json'
```

---

## 10. Feed pencari kerja

**Urutan default: tanggal pembuatan, terbaru dulu.** Nama, jarak, waktu, kategori, dan
keahlian semuanya **filter**, bukan pengubah urutan — itu yang membuat cursor tetap
bertumpu pada kolom berindeks dan hasilnya stabil.

| Filter | Parameter | Lewat apa |
|---|---|---|
| Nama pekerjaan | `q` | indeks terbalik FULLTEXT di `task_search` |
| Jarak | `lat` `lng` `radius_km` | bounding box `(latitude, longitude)`, haversine sesudahnya |
| Waktu | `posted_within_hours` | indeks `(status, created_at)` |
| Kategori | `category_id` | indeks `(category_id, status, created_at)` |
| Keahlian | `skills` / `match_my_skills` | pivot berindeks `skill_task` |

Tidak satu pun memakai `LIKE`.

```bash
curl -s "$BASE/tasks" -H "Authorization: Bearer $AT_PEKERJA" -H 'Accept: application/json'
```

Tiga hal dikecualikan supaya feed ini tidak berbohong:

1. Hanya task yang **benar-benar** bisa dilamar — status `open` saja tidak cukup, masa
   lelangnya bisa sudah lewat.
2. **Bukan task milik sendiri.** Di aplikasi ini satu orang normalnya ada di kedua sisi.
3. Penawaran sendiri disertakan sebagai `my_bid`, jadi UI bisa menandai "sudah dilamar"
   tanpa panggilan kedua.

### Waktu — hanya yang baru diposting

```bash
curl -s -G "$BASE/tasks" -d 'posted_within_hours=24' \
  -H "Authorization: Bearer $AT_PEKERJA" -H 'Accept: application/json'
```

Jam, bukan tanggal: pencari kerja yang memantau feed sepanjang hari berpikir dalam "sejak
tadi pagi". Batasnya dihitung dari **jam server** — tanggal dari perangkat yang jamnya
meleset akan menyembunyikan task yang sebenarnya baru. Maksimum `720` (30 hari), supaya
nilainya tetap bertumpu pada indeks `(status, created_at)` dan tidak berubah jadi "semua
task" yang menyamar sebagai filter.

### Nama pekerjaan — indeks terbalik, bukan `LIKE`

```bash
curl -s -G "$BASE/tasks" --data-urlencode 'q=cuci ac' \
  -H "Authorization: Bearer $AT_PEKERJA" -H 'Accept: application/json'
```

Mencari di judul **dan** deskripsi. Semua kata harus cocok (AND), dan kata terakhir
dicocokkan sebagai awalan — `q=bersih rum` menemukan "bersih rumah". Masukan hanya tanda
baca menghasilkan **nol baris**, bukan seluruh data.

`LIKE '%kata%'` tidak bisa memakai indeks apa pun: biayanya memindai seluruh tabel dan
tumbuh linear terhadap jumlah task. Yang dipakai di sini indeks terbalik — biayanya
sebanding jumlah kecocokan, bukan jumlah baris.

**Teks yang dicari bukan judul mentah.** Ada tabel `task_search` berisi versi judul dan
deskripsi yang sudah dinormalisasi, dan indeks FULLTEXT-nya ada di situ. Tabel terpisah
karena feed memakai `select('tasks.*')`: sebuah kolom teks besar di `tasks` akan ikut
terbaca setiap permintaan padahal tidak pernah ditampilkan. Barisnya dijaga oleh model
`Task` sendiri, jadi tidak ada jalur tulis yang bisa lupa.

Normalisasi itu menutup dua kelemahan FULLTEXT bawaan, keduanya tanpa setelan server:

**Kata pendek.** `innodb_ft_min_token_size` bawaan MySQL adalah **3**, jadi "AC" tidak
akan pernah masuk indeks. Kata di bawah ambang disimpan dengan penanda (`ac` → `zqac`)
sehingga panjangnya cukup, dan kata kunci pendek diubah dengan aturan yang sama saat
mencari. Kecocokannya **persis** — berbeda dari parser ngram, yang membuat `q=ac` ikut
menangkap "macet" dan "acara".

**Imbuhan.** Akar kata ikut disimpan, jadi `q=bersih` menemukan judul "Membersihkan
gudang", dan `q=bersihkan` juga. Pemenggalan hanya dilakukan saat mengindeks, tidak saat
mencari: akar yang salah penggal cuma jadi kata yang tak pernah dicari, sedangkan kalau
dipakai di sisi kueri ia langsung jadi hasil pencarian yang salah di depan pengguna.

> [!note] `innodb_ft_min_token_size = 1` di `/opt/homebrew/etc/my.cnf` mesin ini
> Setelan itu **tidak lagi dibutuhkan** — aplikasinya kini bekerja pada MySQL dengan
> setelan bawaan. Setelan itu juga membuat test pencarian kata pendek tidak membuktikan
> apa pun di mesin ini, karena "ac" akan terindeks apa adanya. Karena itu yang diuji
> adalah invarian-nya: sisi kueri **tidak pernah** mengeluarkan kata di bawah 3 huruf
> (`SearchTermsTest::test_no_query_term_is_shorter_than_the_mysql_minimum`).

### Jarak

```bash
curl -s -G "$BASE/tasks" -d 'lat=-6.1754' -d 'lng=106.8272' -d 'radius_km=5' \
  -H "Authorization: Bearer $AT_PEKERJA" -H 'Accept: application/json' \
  | python3 -c '
import json,sys
for t in json.load(sys.stdin)["data"]:
    print(f"{t[\"title\"][:30]:32} {t[\"distance_km\"]} km")
'
```

- `lat` dan `lng` **wajib berpasangan** — mengirim salah satu saja ditolak `422`, bukan
  diabaikan diam-diam.
- `radius_km` maksimum 100.
- Task yang lolos membawa `distance_km`.
- Koordinat datang dari **GPS perangkat**, bukan dari profil — `users` tidak menyimpan
  koordinat. Itu justru lebih akurat (orang sering mencari kerja saat tidak di rumah),
  tapi berarti pencarian jarak butuh izin lokasi; kalau ditolak, sediakan filter `city`.

### Keahlian

```bash
curl -s -G "$BASE/tasks" -d 'skills=cuci-ac,setrika' \
  -H "Authorization: Bearer $AT_PEKERJA" -H 'Accept: application/json'

# atau cocokkan dengan keahlian di profil saya
curl -s -G "$BASE/tasks" -d 'match_my_skills=1' \
  -H "Authorization: Bearer $AT_PEKERJA" -H 'Accept: application/json'
```

Filter bisa digabung bebas:

```bash
curl -s -G "$BASE/tasks" \
  -d 'lat=-6.1754' -d 'lng=106.8272' -d 'radius_km=5' \
  -d 'match_my_skills=1' --data-urlencode 'q=cuci' \
  -H "Authorization: Bearer $AT_PEKERJA" -H 'Accept: application/json'
```

---

## 11. Pagination

```bash
curl -s -G "$BASE/tasks" -d 'per_page=2' \
  -H "Authorization: Bearer $AT_PEKERJA" -H 'Accept: application/json' | python3 -m json.tool
```

```json
{
  "data": [ … ],
  "links": { "first": null, "last": null, "prev": null, "next": null },
  "meta": {
    "path": "http://127.0.0.1:8000/api/v1/tasks",
    "per_page": 2,
    "next_cursor": "eyJjcmVhdGVkX2F0Ijoi…",
    "prev_cursor": null
  }
}
```

`meta` punya `next_cursor`/`prev_cursor` dan **tidak** punya `current_page`, `total`, atau
`last_page`. Ketiadaannya adalah bukti ini keyset pagination, bukan offset.

`?page=2` **diabaikan**. Hanya `?cursor=` yang memindahkan halaman:

```bash
curl -s -G "$BASE/tasks" -d 'per_page=2' -d "cursor=$NEXT" \
  -H "Authorization: Bearer $AT_PEKERJA" -H 'Accept: application/json'
```

`per_page` maksimum 50; `?per_page=5000` → `422`.

---

## 12. Profil pekerja — tabel sendiri

Satu orang di Sekarya bisa jadi dua-duanya: hari ini memberi kerja, besok mencari kerja.
Yang dipisah ke tabel `user_workers` **bukan orangnya**, melainkan sisi pekerjanya —
nama yang ia tampilkan sebagai pekerja, nomor yang boleh dihubungi pemberi kerja, alamat
tempat ia menerima pekerjaan, lokasi kerja, dan seluruh reputasinya sebagai pekerja.

### Identitas tetap satu

`gender` dan `birth_date` **tidak ada** di profil pekerja, dan tidak bisa dikirim ke
sana. Keduanya identitas orangnya, bukan peran yang sedang ia jalankan — satu orang tidak
berganti tanggal lahir saat berpindah mode. Sumbernya `users`, diubah lewat `PATCH /me`:

```bash
curl -s -X PATCH "$BASE/me" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"gender": "male", "birth_date": "1995-03-02"}' | jq '.data | {gender, birth_date, age}'
```

```json
{ "gender": "male", "birth_date": "1995-03-02", "age": 31 }
```

**`age` dihitung, tidak disimpan, dan tidak bisa dikirim.** Kolom umur akan salah pada
hari ulang tahun setiap penggunanya, dan tidak ada satu pun permintaan HTTP yang datang
karena seseorang bertambah tua — tidak ada yang bisa memicu pembaruannya. Yang disimpan
tanggalnya; umurnya dihitung setiap kali dibaca. Kirim `age` dan ia diabaikan.

Batasnya 17-100 tahun. 17 adalah usia KTP, dan verifikasi identitas di aplikasi ini
mencocokkan dengan KTP — orang yang belum bisa punya KTP tidak akan pernah lolos.
Formatnya persis `YYYY-MM-DD`; tanpa itu `01/02/2003` diterima, dan artinya berbeda di
dua benua.

### Identitas harus lengkap sebelum profil pekerja bisa dibuka

```bash
curl -s -X PUT "$BASE/me/worker" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"radius_km":15}' | jq '{code, missing: .context.missing}'
```

Kalau akunnya belum punya jenis kelamin dan tanggal lahir:

```json
{ "code": "profile_incomplete", "missing": ["gender", "birth_date"] }
```

`422`, dan `context.missing` menyebut **persis** field mana yang kurang — klien menyorot
isian yang tepat tanpa perlu mengurai `message`. Lengkapi lewat `PATCH /me`, lalu ulangi.

Endpoint ini sengaja **tidak** menerima `gender`/`birth_date` sendiri walau satu panggilan
akan lebih enak: identitas hanya boleh punya satu jalur tulis. Dua jalur berarti dua
tempat yang harus sama-sama benar setiap kali aturannya berubah.

Yang sudah terlanjur ada dibiarkan. Mengosongkan tanggal lahir sesudah profil pekerja
dibuat **tidak** menghapus profilnya — reputasi menempel pada baris itu dan tidak bisa
dibangun ulang. Yang menjaga daftar tetap bersih adalah `ready_to_work`, bukan
penghapusan baris.

### Membaca profil pekerja tidak membuat baris

```bash
curl -s "$BASE/me/worker" -H "Authorization: Bearer $AT" -H 'Accept: application/json' \
  | jq '.data | {configured, name, contact_phone, gender, age, own}'
```

Orang yang belum pernah mengisi apa pun tetap mendapat profil **utuh** — seluruhnya
diwarisi dari akun:

```json
{
  "configured": false,
  "name": "Budi Santoso",
  "contact_phone": "+628111000111",
  "gender": "male",
  "age": 31,
  "own": { "display_name": null, "contact_phone": null, "address_line": null }
}
```

Perhatikan dua lapisnya. Di tingkat atas nilai **terpakai**; di `own` yang benar-benar
**diisi sendiri**, `null` kalau diwarisi. Formulir sunting harus memakai `own` sebagai
nilai awal — kalau ia memakai nilai terpakai, sekali disimpan kembali seluruh warisan
berubah jadi nilai tetap, ikatannya ke akun putus diam-diam, dan ganti nama di profil
akun berhenti terlihat di sini.

### `null` berarti "kembali ikut akun"

```bash
curl -s -X PUT "$BASE/me/worker" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"display_name": "Budi Tukang AC", "radius_km": 15,
       "latitude": -6.2088, "longitude": 106.8456}' -w '\nstatus=%{http_code}\n'
```

`201` saat profilnya baru dibuat, `200` pada panggilan berikutnya. Mengirim isi yang sama
dua kali menghasilkan keadaan yang sama, jadi klien tidak perlu tahu lebih dulu apakah
profilnya sudah ada.

Sekarang kembalikan namanya mengikuti akun:

```bash
curl -s -X PUT "$BASE/me/worker" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"display_name": null}' | jq '.data | {name, own: .own.display_name}'
```

```json
{ "name": "Budi Santoso", "own": null }
```

Field yang **tidak disebut** tidak tersentuh. Yang dikirim bernilai `null` kembali
mewarisi.

### Alamat adalah satu kesatuan

Begitu salah satu kolom alamat diisi, **seluruh** alamatnya berhenti mewarisi dari akun:

```bash
curl -s -X PUT "$BASE/me/worker" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"city": "Surabaya"}' | jq '.data.address'
```

```json
{ "address_line": null, "city": "Surabaya", "province": null, "postal_code": null }
```

Kalau tiap kolom jatuh sendiri-sendiri ke akun, pekerja yang menuliskan alamat kerjanya
di kota lain akan mendapat gabungan dua alamat — jalannya dari sini, kotanya dari
domisili akun. Itu alamat yang tidak pernah ada, dan pemberi kerja akan mendatanginya.

### Koordinat hanya sah berpasangan

```bash
curl -s -X PUT "$BASE/me/worker" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"latitude": -6.2088}' -w '\nstatus=%{http_code}\n'
```

`422`. Satu lintang tanpa bujur bukan lokasi yang kurang lengkap — ia bukan lokasi sama
sekali, dan setiap kueri jarak akan melewatinya tanpa memberi tahu siapa pun bahwa orang
ini mengira dirinya sudah terpasang di peta.

### Reputasi tidak bisa dikirim

`worker_rating_avg`, `worker_rating_count`, `tasks_completed` dan `bids_won` dijaga dua
lapis: tidak ada di aturan validasi, dan tidak mass-assignable di modelnya. Yang
menulisnya hanya Action — `AcceptBidAction` (menang lelang), `ApproveActivityAction`
(pekerjaan disetujui), dan `CreateReviewAction` (dihitung ulang dari tabel `reviews`).
Reputasi yang bisa dikirim klien bukan reputasi; ia kolom isian.

Kirim saja, dan ia diabaikan:

```bash
curl -s -X PUT "$BASE/me/worker" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"tasks_completed": 999}' | jq '.data.as_worker'
```

```json
{ "rating_avg": 0, "rating_count": 0, "tasks_completed": 0, "bids_won": 0 }
```

### `ready_to_work` — dua syarat, dan dihitung

Setiap profil (`/me`, profil publik, dan sisi pengelola) membawa `ready_to_work`. Ia
`true` hanya kalau **dua-duanya** terpenuhi:

1. ada baris di `user_workers` — profil pekerjanya sudah dibuka, dan
2. ada verifikasi `identity` berstatus `verified`.

Punya profil saja tidak cukup, dan itu disengaja: siapa pun bisa membuat profil sendiri
lewat satu panggilan. Yang membuatnya berarti adalah persetujuan pengelola atas
identitasnya — dan itu tidak bisa diberikan sendiri. Verifikasi yang **dicabut** langsung
mematikan penanda ini, tanpa ada yang perlu menyentuh baris profilnya.

Rekening bank tidak ikut jadi syarat: ia syarat untuk **dibayar**, bukan untuk boleh
bekerja. Menggabungkannya berarti pekerja tidak bisa melamar apa pun sampai dua antrean
manual selesai.

Ia **diturunkan**, bukan kolom boolean di `users`. Kolom akan menjawab dari tempat yang
bukan sumbernya dan bisa melenceng lewat jalur mana pun yang membuat, menghapus, atau
mencabut. Yang tertinggal bukan sekadar angka salah: ia pekerja yang muncul di
`GET /workers` padahal haknya sudah dicabut.

```bash
curl -s "$BASE/me" -H "Authorization: Bearer $AT" -H 'Accept: application/json' \
  | jq '.data.ready_to_work'
```

### Daftar pekerja — sisi sebaliknya dari feed task

```bash
curl -s "$BASE/workers?per_page=2&city=Jakarta&gender=female" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' \
  | jq '{orang: [.data[].name], next: .meta.next_cursor}'
```

Yang muncul: setiap akun **`active`** yang punya profil pekerja — terverifikasi maupun
belum. **Verifikasi identitas menandai, tidak menyaring**: yang belum diverifikasi tetap
tampil dengan `ready_to_work: false`. Menyembunyikannya berarti pekerja baru tidak akan
pernah mendapat pekerjaan pertamanya, dan verifikasi berubah dari penanda kepercayaan
menjadi syarat masuk yang tidak pernah disebut ke siapa pun.

Yang tetap menyaring hanya status akun. Akun yang ditangguhkan masih punya barisnya, dan
daftar ini tempat pemberi kerja memilih orang untuk dihubungi — moderasi yang tidak
terbaca di sini adalah moderasi yang tidak berlaku.

Pemberi kerja yang memang hanya mau yang terverifikasi menambahkan `ready_to_work=true`;
`ready_to_work=false` menyisir yang belum, berguna untuk pengelola.

Tiga hal yang menentukan bentuk endpoint ini:

**Urutannya "yang baru SIAP BEKERJA", bukan "yang baru mendaftar".** Kuncinya
`user_workers.created_at` + `id`, bukan `users.created_at`. Seseorang bisa punya akun dua
tahun lalu dan baru kemarin membuka profil pekerjanya — dan dialah yang justru dicari.

**Cursor, bukan offset.** `meta` memuat `next_cursor` dan tidak pernah memuat `total`
maupun `current_page`, sama seperti seluruh endpoint daftar di API ini. Cursor-nya opaque;
jangan pernah disusun sendiri di sisi klien.

**Penyaring kota membandingkan alamat TERPAKAI**, bukan kolom profilnya saja. Alamat kerja
bawaannya diwarisi dari akun, jadi menyaring `user_workers.city` saja akan menghilangkan
hampir setiap pekerja — daftarnya akan tampak bekerja sambil hampir selalu kosong.

Bentuk barisnya **identik dengan profil publik** (`PublicUser`), bukan bentuk tersendiri.
Daftar inilah yang mengembalikan paling banyak orang sekaligus, jadi batas pengungkapan
yang didefinisikan dua kali akan bocor justru di tempat yang paling mahal.

### Apa yang dilihat orang lain

Pemberi kerja yang menimbang penawaran melihat `PublicUser`, dan batasnya lebih ketat:

| Field | Sendiri (`/me`, `/me/worker`) | Orang lain | Pengelola |
|---|---|---|---|
| `gender` | ✅ | ✅ | ✅ |
| `age` | ✅ | ✅ | ✅ |
| `birth_date` | ✅ | ❌ | ✅ |
| Alamat jalan | ✅ | ❌ | ❌ |
| Kota kerja + radius | ✅ | ✅ (`as_worker.work_area`) | ❌ |
| Koordinat lokasi kerja | ✅ | ❌ | ❌ |
| Nomor kontak | ✅ | ❌ | ✅ (nomor akun) |

Umur adalah bahan pertimbangan yang wajar — pekerjaan angkat-angkut, jaga malam. Tanggal
lahir persis adalah bahan pembobolan identitas: bank dan layanan publik memakainya
sebagai verifikasi. Pengelola melihat tanggalnya karena verifikasi identitas
mencocokkannya dengan yang tertera di KTP, dan umur saja tidak bisa dicocokkan dengan
apa pun.

## 13. Verifikasi identitas

```bash
curl -s -X POST "$BASE/me/verifications" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{
    "type": "identity",
    "id_card_photo_path": "verifications/ktp-abc.jpg",
    "selfie_photo_path": "verifications/selfie-abc.jpg",
    "document_number": "3174012345678901",
    "name_on_document": "Budi Prasetyo"
  }' -w '\nstatus=%{http_code}\n'
```

`202`. Foto KTP dan selfie dikirim **bersamaan** — gunanya selfie justru untuk dicocokkan
dengan foto di KTP, jadi memisahkannya jadi dua pengajuan tidak berguna.

Sekarang buktikan tidak ada yang bocor:

```bash
BODY=$(curl -s "$BASE/me/verifications" -H "Authorization: Bearer $AT" -H 'Accept: application/json')
for n in 'ktp-abc' 'selfie-abc' '3174012345678901' 'photo_path' 'document_number'; do
  echo "$BODY" | grep -q "$n" && echo "BOCOR: $n" || echo "aman : $n"
done
```

```
aman : ktp-abc
aman : selfie-abc
aman : 3174012345678901
aman : photo_path
aman : document_number
```

Yang keluar hanya **status**:

```json
{ "data": [{ "type": "identity", "status": "pending", "is_verified": false,
             "has_id_card_photo": true, "has_selfie_photo": true }] }
```

> [!important] Foto verifikasi ≠ avatar
> `avatar_path` adalah foto **publik**, tampil di kartu penawaran. Foto verifikasi hanya
> boleh dilihat pemiliknya dan admin, lewat signed URL terpisah. Selfie memegang KTP
> memuat NIK dan alamat yang **terbaca di foto** — memakainya sebagai avatar akan
> menerbitkan data itu.

NIK sendiri disimpan dua kali dengan tujuan berbeda: **hash** untuk mendeteksi satu NIK
dipakai beberapa akun, dan **terenkripsi** untuk dibaca manusia saat penilaian atau
sengketa. Tidak pernah dalam bentuk mentah.

Yang menilai pengajuan ini adalah **pengelola** — bagian 15.

---

## 14. Rate limit & CORS

Setiap endpoint dibatasi. Responsnya membawa kuota:

```bash
curl -si -X POST "$BASE/auth/login" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"x@sekarya.test","password":"salah"}' | grep -i x-ratelimit
```

```
X-RateLimit-Limit: 5
X-RateLimit-Remaining: 4
```

Percobaan ke-6 → `429` dengan `Retry-After`.

| Kelompok | /menit | Kunci |
|---|---|---|
| Endpoint aplikasi | 120 | pengguna |
| `login` | 5 | **email + IP** |
| `register` | 5 | IP |
| `verify-email` | 6 | email + IP |
| `resend-code` | 3 | email |
| `refresh` | 10 | pengguna |
| Buat task & penawaran | 30 | pengguna |
| Endpoint `/admin` | 240 | pengelola |
| `admin/auth/login` | 5 | **email + IP** |

Kunci pembatas pengelola diberi awalan `admin|`, supaya kuotanya tidak pernah berbagi
ember dengan kuota pengguna: id keduanya bigint dari dua tabel berbeda, jadi admin id 7
dan user id 7 akan saling menghabiskan kuota tanpa ada yang tahu.

Login dikunci per **email + IP**, bukan per email saja. Kalau per email, penyerang bisa
mengunci akun korban dari akunnya sendiri hanya dengan membanjiri percobaan gagal.

CORS — origin terdaftar dapat header, yang lain tidak:

```bash
curl -si -X OPTIONS "$BASE/me" -H 'Origin: http://localhost:3000' \
  -H 'Access-Control-Request-Method: GET' | grep -i access-control
curl -si "$BASE/categories" -H 'Origin: https://evil.example' | grep -ci access-control
```

Daftar origin dari `CORS_ALLOWED_ORIGINS` (dipisah koma), **jangan `*`** di produksi.
`supports_credentials` sengaja `false` — API ini memakai Bearer token, bukan cookie, dan
membiarkannya `false` menutup seluruh kelas CSRF lintas asal.

---

## 15. Konsol pengelola

Pengelola **bukan pengguna dengan peran**. Ia baris di tabelnya sendiri (`admins`),
dipegang guard-nya sendiri, dan tokennya tidak berlaku di endpoint pengguna — begitu pula
sebaliknya.

| Token | Endpoint pengguna | Endpoint `/admin` |
|---|---|---|
| `access_token` pengguna | `200` | **`401`** |
| `access_token` pengelola | **`401`** | `200` |

`401`, bukan `403`: guard `/admin` sama sekali tidak mengenali pemilik token dari tabel
pengguna, jadi kegagalannya di autentikasi.

> [!warning] Kenapa ini perlu ditulis besar-besar
> Sanctum mendaftarkan guard-nya sendiri dengan `provider => null` kalau `config/auth.php`
> tidak menyebutkannya, dan dengan provider null ia **meloloskan pemilik token jenis apa
> pun**. Satu baris config yang hilang cukup untuk membuat token pengguna berlaku di
> seluruh `/admin`, tanpa galat dan tanpa jejak. Karena itu ability tokennya juga
> dipisah (`admin:access` vs `token:access`) — lapis kedua yang tetap bekerja kalau lapis
> pertama hilang.

### Dua peran

| Peran | Jumlah | Bisa dihapus? | Boleh apa |
|---|---|---|---|
| `super_admin` | **tepat satu** | **tidak** | semuanya, termasuk membuat & menghapus pengelola |
| `admin` | berapa pun | ya | verifikasi, konfirmasi transfer, moderasi pengguna |

"Tepat satu" dijamin **basis data**, bukan disiplin kode: kolom turunan
`super_admin_lock` berisi `'s'` hanya untuk baris super_admin, dan indeks unique atasnya
menolak baris kedua dengan `#1062`.

### Akun pertama: hanya dari baris perintah

Tidak ada endpoint pendaftaran pengelola, dan seeder-nya sengaja tidak ada — berkas
seeder dan `database/schema/sekarya-install.sql` sama-sama dilacak git, jadi kredensial
di dalamnya bisa dibaca siapa pun yang membuka repositori.

```bash
php artisan sekarya:admin create \
  --name="Super Admin" --email=super@sekarya.test --password='RahasiaKuatSekali99!'
```

Tanpa `--password`, sandinya ditanyakan tanpa ditampilkan. Tanpa opsi sama sekali,
nilainya diambil dari `SEKARYA_SUPER_ADMIN_*` di `.env`.

Di shared hosting tanpa SSH, perintah ini dijalankan lewat cron — dan di sana ia **wajib**
memakai `--no-interaction` serta menulis log, karena cron tidak punya keyboard dan tidak
menampilkan apa pun. Bentuk lengkapnya di [`DEPLOYMENT.md` bagian 7](DEPLOYMENT.md).

```bash
php artisan sekarya:admin list                              # siapa saja yang ada
php artisan sekarya:admin suspend --email=verif@sekarya.test # nonaktifkan + cabut token
php artisan sekarya:admin activate --email=verif@sekarya.test
```

Sandi contoh yang ikut terlacak git **ditolak** saat `APP_ENV=production`.

### Masuk

```bash
export ADMIN_AT=$(curl -s -X POST "$BASE/admin/auth/login" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"super@sekarya.test","password":"RahasiaKuatSekali99!"}' \
  | python3 -c 'import json,sys; print(json.load(sys.stdin)["data"]["access_token"])')

curl -s "$BASE/admin/me" -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json'
```

Pasangan tokennya sama seperti pengguna: `access` 8 jam, `long_lived` 30 hari yang hanya
bisa memanggil `POST /admin/auth/refresh`.

Statusnya diperiksa **di setiap permintaan**, bukan hanya saat login — token hidup
delapan jam dan tidak menyimpan status di dalamnya, jadi tanpa itu pencabutan kewenangan
baru berlaku delapan jam kemudian:

```bash
php artisan sekarya:admin suspend --email=verif@sekarya.test
# permintaan berikutnya dengan token yang sama:
# 403 {"message":"Akun pengelola ini dinonaktifkan.","code":"admin_access_denied",
#      "context":{"reason":"suspended"}}
```

### Antrean verifikasi

```bash
curl -s "$BASE/admin/verifications" -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json'
```

**Paling lama menunggu di depan** — kebalikan dari endpoint daftar lain di API ini.
Antrean kerja yang menampilkan terbaru dulu membuat pengajuan tertua tenggelam makin
dalam setiap ada pengajuan baru, dan justru itulah pengajuan yang paling lama membuat
orang tidak bisa bekerja. Tanpa `?status=`, yang tampil hanya yang belum diputuskan.

Daftar ini **tidak** memuat NIK, nomor rekening, maupun path foto. Detailnya yang memuat:

```bash
curl -s "$BASE/admin/verifications/1" -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json'
```

```json
{ "data": { "id": 1, "type": "identity", "status": "pending",
            "name_on_document": "BUDI PRASETYO",
            "document_number": "3271012345678901",
            "user": { "email": "budi@sekarya.test", "status": "active" } } }
```

> [!important] Permintaan `GET` ini **menulis**
> Detail inilah satu-satunya tempat di seluruh API yang mengeluarkan NIK dan nomor
> rekening dalam bentuk terbaca — karena tanpa nomornya, "verifikasi identitas" hanya
> bisa dijawab dengan menebak. Yang membuatnya bisa dipertanggungjawabkan: setiap
> pembacaan meninggalkan baris `verification.viewed` di `admin_audit_logs` — siapa
> membuka data siapa, kapan, dari IP mana. Keputusan bisa ditinjau dari statusnya;
> pembacaan tidak meninggalkan bekas apa pun kalau tidak dicatat.

Keputusannya ditentukan **endpoint**-nya, bukan payload:

```bash
curl -s -X POST "$BASE/admin/verifications/1/approve" -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json'

curl -s -X POST "$BASE/admin/verifications/1/reject" \
  -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"reason":"Foto KTP tidak terbaca, silakan unggah ulang."}'
```

`reason` wajib (minimal 10 karakter) dan **dibaca penggunanya sendiri** di
`GET /me/verifications` — tanpa itu, orang yang ditolak tidak punya cara tahu apa yang
harus diperbaiki. Penolakan tidak bisa dibatalkan di baris yang sama; perbaikannya lewat
pengajuan ulang, yang meninggalkan riwayat penolakannya.

`revoke` menarik kembali verifikasi yang sudah diberikan — untuk identitas yang ternyata
palsu. Tanpa jalur ini, badge terverifikasi tidak bisa ditarik setelah diberikan.

> [!warning] Fotonya belum bisa dilihat
> `has_id_card_photo` hanya mengatakan fotonya ada. Endpoint yang menyajikan berkasnya
> lewat signed URL **belum ada** — ia menyusul bersama slice unggah berkas. Sampai saat
> itu, penilaian identitas bertumpu pada data teks yang diketik pemiliknya.

### Antrean konfirmasi transfer

```bash
curl -s "$BASE/admin/payments" -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json'
```

Bawaannya `awaiting_confirmation` — satu-satunya status yang menunggu tindakan manusia.
Urut `reported_at`, bukan `created_at`: baris tagihan dibuat saat pelamar pertama
diterima, jauh sebelum ada transfer yang dilaporkan.

```bash
curl -s -X POST "$BASE/admin/payments/$PAY/confirm" -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json'
curl -s -X POST "$BASE/admin/payments/$PAY/reject" \
  -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"reason":"Tidak ada mutasi masuk sejumlah itu hari ini."}'
```

Rinciannya di bagian 7.

### Moderasi pengguna

```bash
curl -s "$BASE/admin/users?email=budi@sekarya.test" -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json'
```

`email` adalah pencocokan **persis**, bukan pencarian sebagian: `%budi%` tidak bisa
memakai indeks apa pun, jadi setiap ketikan akan memindai seluruh tabel pengguna — dan
proyek ini memang tidak memakai `LIKE` di mana pun. Alamat yang tidak terdaftar
mengembalikan daftar kosong, bukan `422`; kalau `422`, daftar ini menjadi alat menebak
alamat terdaftar.

```bash
curl -s -X POST "$BASE/admin/users/$USER/suspend" \
  -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"reason":"Melaporkan transfer palsu dua kali berturut-turut."}'
```

**Seluruh token pengguna itu dicabut**, dan itulah yang benar-benar menghentikannya.
Kolom status saja tidak: access token hidup delapan jam dan tidak menyimpan status di
dalamnya, jadi tanpa pencabutan token akun yang ditangguhkan tetap bisa menawar dan
mengerjakan task sampai tokennya kedaluwarsa — long_lived-nya bahkan 30 hari.

`reinstate` memulihkan, tapi **belum tentu ke `active`**: akun yang belum pernah
memverifikasi email dikembalikan ke `pending_verification` dan harus menyelesaikan
kodenya sendiri. Kalau dikembalikan ke `active`, moderasi menjadi jalan melewati
verifikasi email — tangguhkan lalu pulihkan, dan akunnya aktif tanpa pernah memasukkan
kode.

### Akun pengelola lain — hanya super_admin

```bash
curl -s -X POST "$BASE/admin/admins" \
  -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"name":"Verifikator Dua","email":"verif2@sekarya.test",
       "password":"RahasiaKuatSekali99!","password_confirmation":"RahasiaKuatSekali99!"}'
```

`201`, dan perannya **selalu** `admin`. Tidak ada field `role` — kalau peran bisa dikirim
klien, endpoint ini adalah jalan membuat super_admin kedua, dan yang menahannya cuma
aturan validasi. Mengirimkannya tetap menghasilkan `admin`.

Sandinya lebih ketat daripada sandi pengguna: minimal 12 karakter, campuran huruf
besar-kecil, angka, simbol, dan dicek terhadap basis data kebocoran sandi publik. Yang
dijaga di sini bukan satu akun belanja, tapi kewenangan menyetujui uang.

```bash
curl -s -X DELETE "$BASE/admin/admins/$ID" -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json'
```

Soft delete, dan tokennya dicabut. Barisnya tetap ada karena `admin_audit_logs`
menunjuknya — "siapa menyetujui pembayaran ini" yang menunjuk baris yang sudah hilang
tidak bisa dibaca lagi. Akibat yang disengaja: **alamat emailnya tetap terpakai** dan
tidak bisa diberikan kepada orang lain.

super_admin sendiri ditolak, `403 super_admin_protected`. Peran `admin` yang mencoba
menyentuh kelompok ini ditolak `403 admin_access_denied` dengan
`context.reason: insufficient_role`.

### Jejak audit

Setiap keputusan pengelola menulis satu baris di `admin_audit_logs`: pelakunya,
tindakannya, baris yang disentuh, alasannya, IP, dan waktunya. Tabel itu append-only —
tidak ada `updated_at`, karena jejak yang bisa disunting bukan jejak — dan barisnya tidak
bisa dihapus dengan cara menghapus pelakunya.

**Belum ada endpoint untuk membacanya.** Sampai ada, isinya dibaca langsung dari basis
data:

```sql
SELECT a.email, l.action, l.subject_type, l.subject_id, l.reason, l.ip, l.created_at
FROM admin_audit_logs l JOIN admins a ON a.id = l.admin_id
ORDER BY l.created_at DESC LIMIT 20;
```

---

## Ringkasan endpoint

**60 endpoint, satu baris masing-masing.** Daftar ini dibangkitkan dari
`php artisan route:list`, dan sebuah test menjaganya tetap seiring: menambah rute tanpa
mendaftarkannya di `docs/openapi.yaml` membuat suite gagal
(`tests/Feature/Docs/ApiDocumentationTest.php`).

Semua di bawah `/api/v1`. Kolom **Token**: `access` = token pendek 8 jam, `long_lived` =
token 30 hari yang HANYA bisa refresh, `admin` = token pengelola, `—` = tanpa token.
Kolom **Limit** menyebut pembatas laju yang berlaku; angkanya di `config/sekarya.php`.

> [!important] 38 endpoint pertama untuk PENGGUNA, 22 terakhir untuk PENGELOLA, dan
> tokennya **tidak bisa ditukar**. Akun pengelola ada di tabelnya sendiri dengan
> guard-nya sendiri: token pengguna di `/admin` menghasilkan `401`, dan token pengelola
> di endpoint pengguna juga `401`. Lihat bagian **Pengelola** di bawah.

**Auth — tanpa token, kecuali dua terakhir**

| | Endpoint | Token | Limit | Keterangan |
|---|---|---|---|---|
| `POST` | `/auth/login` | — | `login` | Masuk. Balasan sama untuk kata sandi salah maupun email tak dikenal. |
| `POST` | `/auth/logout` | kedua token | `api` | Keluar. Mencabut **kedua** token, termasuk yang berumur panjang. |
| `POST` | `/auth/refresh` | long_lived | `refresh` | Tukar `long_lived` jadi `access` baru. Access token lama langsung mati. |
| `POST` | `/auth/register` | — | `register` | Daftar akun. `202`, **tanpa token** — akun belum aktif. |
| `POST` | `/auth/resend-code` | — | `resend` | Kirim ulang kode. Balasan sama untuk email dikenal maupun tidak. |
| `POST` | `/auth/verify-email` | — | `verify` | Masukkan kode dari email. Satu-satunya jalan ke `active` + pasangan token. |

**Katalog & akun**

| | Endpoint | Token | Limit | Keterangan |
|---|---|---|---|---|
| `GET` | `/categories` | access | `api` | Katalog kategori + harga referensi. |
| `GET` | `/me` | access | `api` | Profil sendiri, lengkap dengan data kontak. |
| `PATCH` | `/me` | access | `api` | Ubah profil. `extras` divalidasi per peran. |
| `GET` | `/me/worker` | access | `api` | Profil pekerja sendiri. Membacanya tidak membuat baris. |
| `PUT` | `/me/worker` | access | `api` | Isi/ubah profil pekerja. `null` = kembali ikut akun. |
| `GET` | `/me/verifications` | access | `api` | Status verifikasi identitas. Hanya status, bukan artefaknya. |
| `POST` | `/me/verifications` | access | `api` | Ajukan verifikasi identitas (KTP, selfie, rekening). |
| `GET` | `/skills` | access | `api` | Katalog keahlian. |
| `GET` | `/workers` | access | `api` | Daftar pekerja. Filter: `city`, `province`, `gender`, `ready_to_work`. Cursor. |

**Task**

| | Endpoint | Token | Limit | Keterangan |
|---|---|---|---|---|
| `GET` | `/tasks` | access | `api` | Feed siap dilamar. Filter: `q`, `lat`/`lng`/`radius_km`, `posted_within_hours`, `category_id`, `city`, `budget_from`/`budget_to`, `skills`, `match_my_skills`, `exclude_my_bids`. |
| `POST` | `/tasks` | access | `write` | Buat task. `workers_needed` menentukan berapa orang direkrut. |
| `GET` | `/tasks/posted` | access | `api` | Task yang saya posting. |
| `GET` | `/tasks/worked` | access | `api` | Task yang saya kerjakan. |
| `GET` | `/tasks/{task}` | access | `api` | Detail satu task, termasuk `hiring`, `workers`, `payment`, `activities`. |
| `POST` | `/tasks/{task}/cancel` | access | `api` | Batalkan. Dana dikembalikan, penawaran ditutup. |
| `POST` | `/tasks/{task}/publish` | access | `api` | `draft` -> `open`. Lelang dibuka. |
| `POST` | `/tasks/{task}/start` | access | `api` | Berhenti merekrut lebih awal: target turun ke jumlah yang sudah diterima. |

**Lelang**

| | Endpoint | Token | Limit | Keterangan |
|---|---|---|---|---|
| `GET` | `/bids/mine` | access | `api` | Penawaran saya di seluruh task. |
| `POST` | `/bids/{bid}/accept` | access | `api` | **Terima seorang pelamar.** Slot terakhir menutup lelang. |
| `POST` | `/bids/{bid}/withdraw` | access | `api` | Tarik penawaran sendiri. Kuotanya kembali. |
| `GET` | `/tasks/{task}/bids` | access | `api` | Daftar penawaran masuk. `sort=amount`, `rating`, atau `newest`. |
| `POST` | `/tasks/{task}/bids` | access | `write` | Ajukan penawaran. Mengirim ulang **mengubah** penawaran (`200`). |

**Uang**

| | Endpoint | Token | Limit | Keterangan |
|---|---|---|---|---|
| `GET` | `/tasks/{task}/payment` | access | `api` | Status uang task. Satu tagihan untuk seluruh pekerja. Memuat `rejection_reason` kalau laporan sebelumnya ditolak. |
| `POST` | `/tasks/{task}/payment/hold` | access | `api` | **Lapor** sudah transfer -> masuk antrean pengelola. Tidak lagi menahan dana. |

**Pengerjaan**

| | Endpoint | Token | Limit | Keterangan |
|---|---|---|---|---|
| `GET` | `/activities/mine` | access | `api` | Pekerjaan yang saya kerjakan. |
| `GET` | `/activities/{activity}` | access | `api` | Detail satu activity. |
| `POST` | `/activities/{activity}/approve` | access | `api` | Setujui hasil. Dana dilepas saat pekerja **terakhir** disetujui. |
| `POST` | `/activities/{activity}/reject` | access | `api` | Tolak hasil. Task jadi `disputed`, dana tetap ditahan. |
| `POST` | `/activities/{activity}/start` | access | `api` | Pekerja mulai bekerja. |
| `POST` | `/activities/{activity}/submit` | access | `api` | Serahkan hasil + bukti foto. |

**Penilaian**

| | Endpoint | Token | Limit | Keterangan |
|---|---|---|---|---|
| `POST` | `/tasks/{task}/reviews` | access | `api` | Beri penilaian. Pemberi kerja menyebut `worker_id` bila pekerjanya banyak. |
| `GET` | `/users/{user}/reviews` | access | `api` | Penilaian yang diterima seseorang. `role=worker` atau `role=poster`. |

**Pengelola — token `admin`, populasi terpisah**

| | Endpoint | Token | Limit | Keterangan |
|---|---|---|---|---|
| `POST` | `/admin/auth/login` | — | `admin_login` | Masuk sebagai pengelola. Balasan sama untuk sandi salah maupun alamat tak dikenal. |
| `POST` | `/admin/auth/logout` | kedua token admin | `admin` | Keluar. Mencabut **kedua** token. Tetap bisa dipakai akun yang sudah dinonaktifkan. |
| `POST` | `/admin/auth/refresh` | admin long_lived | `refresh` | Tukar jadi `access` baru. Status akun diperiksa ulang di sini. |
| `GET` | `/admin/me` | admin | `admin` | Pengelola yang sedang masuk. |
| `GET` | `/admin/verifications` | admin | `admin` | Antrean verifikasi. **Paling lama menunggu di depan.** Tanpa `status`: yang belum diputuskan. |
| `GET` | `/admin/verifications/{verification}` | admin | `admin` | Detail — satu-satunya tempat NIK & nomor rekening terbaca. **Pembacaannya dicatat.** |
| `POST` | `/admin/verifications/{verification}/approve` | admin | `admin` | Setujui. Badge terverifikasi pemiliknya menyala. |
| `POST` | `/admin/verifications/{verification}/reject` | admin | `admin` | Tolak. `reason` wajib — penggunanya yang membacanya. |
| `POST` | `/admin/verifications/{verification}/revoke` | admin | `admin` | Cabut verifikasi yang sudah diberikan. `reason` wajib. |
| `GET` | `/admin/payments` | admin | `admin` | Antrean konfirmasi transfer. Urut `reported_at`, paling lama menunggu di depan. |
| `GET` | `/admin/payments/{payment}` | admin | `admin` | Detail satu tagihan beserta task dan pembayarnya. |
| `POST` | `/admin/payments/{payment}/confirm` | admin | `admin` | **Satu-satunya jalan ke `held`** -> activity dibuka untuk setiap pekerja. |
| `POST` | `/admin/payments/{payment}/reject` | admin | `admin` | Dana tidak ditemukan. Kembali ke `pending`, `reason` dibaca pemberi kerja. |
| `GET` | `/admin/users` | admin | `admin` | Daftar pengguna. Filter `status`, `email` (**pencocokan persis**, bukan pencarian). |
| `GET` | `/admin/users/{user}` | admin | `admin` | Detail satu pengguna. |
| `POST` | `/admin/users/{user}/suspend` | admin | `admin` | Tangguhkan **dan cabut seluruh tokennya**. `reason` wajib. |
| `POST` | `/admin/users/{user}/ban` | admin | `admin` | Blokir **dan cabut seluruh tokennya**. `reason` wajib. |
| `POST` | `/admin/users/{user}/reinstate` | admin | `admin` | Pulihkan. Akun yang belum verifikasi email kembali ke `pending_verification`. |
| `GET` | `/admin/admins` | admin | `admin` | Daftar akun pengelola. **Hanya `super_admin`.** |
| `POST` | `/admin/admins` | admin | `admin` | Buat pengelola baru. **Hanya `super_admin`.** Perannya dipaksa `admin`. |
| `GET` | `/admin/admins/{admin}` | admin | `admin` | Detail satu akun pengelola. **Hanya `super_admin`.** |
| `DELETE` | `/admin/admins/{admin}` | admin | `admin` | Hapus pengelola. **Hanya `super_admin`**, dan `super_admin` tidak bisa dihapus. |

Tidak ada `POST /admin/auth/register`, dan itu disengaja: akun pengelola hanya lahir dari
`php artisan sekarya:admin create` (super_admin, sekali seumur pemasangan) dan
`POST /admin/admins`.

Health check tanpa prefix: `GET /up`. Referensi ter-render: `GET /docs`, spec mentah:
`GET /docs/openapi.yaml` — keduanya hanya terdaftar **di luar produksi**.

## Kode galat

Bercabanglah pada `code`, **jangan** pada `message`.

| `code` | HTTP | Arti |
|---|---|---|
| — (`errors`) | 422 | Bentuk request tidak sah |
| `unauthenticated` | 401 | Token tidak ada/kedaluwarsa → panggil refresh |
| `invalid_credentials` | 401 | Email/HP atau kata sandi salah |
| `email_not_verified` | 403 | Belum verifikasi kode |
| `account_not_active` | 403 | Akun suspended/banned |
| `invalid_verification_code` | 422 | Kode salah, kedaluwarsa, atau percobaan habis |
| `resend_too_soon` | 429 | Cooldown kirim ulang |
| `task_not_found` | 404 | Tidak ada, **atau** bukan milik Anda |
| `task_not_biddable` | 422 | Tidak menerima penawaran / lelang tutup |
| `cannot_bid_own_task` | 403 | Menawar task sendiri |
| `bid_below_minimum` | 422 | Di bawah `budget_min` |
| `task_already_dealt` | 409 | Seluruh slot sudah terisi |
| `no_workers_hired` | 422 | Mulai lebih awal tanpa satu pun pelamar diterima |
| `review_target_required` | 422 | Task banyak pekerja, `worker_id` harus disebut |
| `payment_not_held` | 422 | Dana tidak lagi ditahan |
| `invalid_status_transition` | 422 | Perpindahan status tidak diizinkan |
| `review_not_allowed` | 422 | Belum selesai, atau sudah menilai |
| `admin_access_denied` | 403 | Pengelola dinonaktifkan, atau perannya tidak mencakup tindakan itu (`context.reason`) |
| `super_admin_protected` | 403 | `super_admin` tidak bisa dihapus maupun dinonaktifkan |

`task_not_found` sengaja `404`, bukan `403` — `403` akan mengonfirmasi bahwa task milik
orang lain itu ada.

---

## Membaca spec di browser

```bash
php artisan serve
# http://127.0.0.1:8000/docs               referensi ter-render
# http://127.0.0.1:8000/docs/openapi.yaml  spec mentah
```

Rute itu terdaftar **hanya di luar produksi** (`routes/web.php`) — spec memuat seluruh
endpoint dan kode galat, jadi menerbitkannya sebaiknya keputusan sadar. Ada test yang
memboot environment produksi di subprocess dan memastikan rute-rute itu hilang.

Tanpa server:

```bash
open docs/api.html
npx --yes -p @redocly/cli redocly build-docs docs/openapi.yaml -o docs/api.html   # regenerasi
npx --yes -p @redocly/cli redocly lint docs/openapi.yaml                          # harus bersih
```

`docs/api.html` adalah berkas hasil generate — masukkan `.gitignore`; `openapi.yaml` yang
jadi sumber kebenaran.

---

## Kalau ada yang aneh

```bash
php artisan route:list --path=api    # rute + middleware
php artisan about --only=environment
tail -f storage/logs/laravel.log     # termasuk kode verifikasi saat MAIL_MAILER=log
bash docs/smoke.sh                   # 162 pemeriksaan
```

Audit lapisan pengamanan — daftar yang keluar harus kosong atau bisa dijelaskan:

```bash
php artisan route:list --path=api --json | python3 -c "
import json,sys
rows = json.load(sys.stdin)
def has(r, n): return any(n in m for m in r['middleware'])
print('tanpa auth     :', [r['uri'] for r in rows if not has(r,'Authenticate')])
print('tanpa ability  :', [r['uri'] for r in rows if has(r,'Authenticate') and not has(r,'CheckAbilities')])
print('tanpa throttle :', [r['uri'] for r in rows if not has(r,'Throttle')])
"
```

Yang wajar muncul: empat endpoint `auth/*` (tanpa auth), `auth/logout` (tanpa ability,
supaya bisa keluar walau access token mati), dan dua rute `docs/*` yang memang hanya hidup
di luar produksi.

## Belum ada

- **Reset kata sandi** — belum ada endpoint lupa/ganti kata sandi.
- **Pembayaran sungguhan.** `payments` masih penanda status. Gateway, kunci idempoten,
  komisi, pencairan ke rekening, pelepasan otomatis, dan refund sebagian belum ada.
- **Penyelesaian sengketa.** `reject` membuat task `disputed` dan dana tetap ditahan;
  belum ada jalan keluar dari status itu lewat API.
- **Chat.** Diputuskan memakai database terpisah; kaitkan lewat `tasks.ulid`.
- **Notifikasi**, alamat tersimpan, urut berdasarkan jarak, dan penutup lelang otomatis
  (task yang `bidding_closes_at`-nya lewat disembunyikan dari feed, tapi statusnya tetap
  `open` sampai ada job yang mengubahnya).
