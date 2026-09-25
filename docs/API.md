# Sekarya API — Panduan

Semua yang ada di dokumen ini dijalankan terhadap kode ini, bukan disusun dari ingatan.

| | |
|---|---|
| **Base URL** | `http://127.0.0.1:8000/api/v1` |
| **Kontrak mesin** | [`docs/openapi.yaml`](openapi.yaml) — OpenAPI 3.1, lint bersih, 116 operation cocok dengan 116 rute nyata |
| **Uji otomatis** | `bash docs/smoke.sh` — 194 pemeriksaan |
| **Database** | MySQL 8+ / InnoDB |
| **Wajib di setiap request** | `Accept: application/json` — tanpa ini Laravel bisa membalas HTML |

Kalau dokumen ini dan spec berselisih, **spec plus `php artisan test` yang benar.**

---

## Cara tercepat memastikan semuanya jalan

```bash
bash docs/smoke.sh
```

Menjalankan server sendiri, mereset database, mendaftar akun lewat alur auth yang
sebenarnya, menjalankan 194 pemeriksaan, lalu membereskan diri. Keluaran akhir:

```
SEMUA LULUS  194/194 pemeriksaan
```

Kalau mau memakai server yang sudah jalan: `bash docs/smoke.sh 8000`.

> [!warning] Smoke test menjalankan `migrate:fresh --seed` — seluruh data dev hilang.

Sisa dokumen ini untuk mencoba manual.

---

## 0. Persiapan

```bash
# sekali saja
mysql -u root -e "CREATE DATABASE IF NOT EXISTS sekarya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate:fresh --seed     # 30 tabel + 9 kategori + 42 keahlian
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
    "gender":"prefer_not_to_say",
    "city":"Jakarta",
    "province":"DKI Jakarta"
  }' -w '\nstatus=%{http_code}\n' | python3 -m json.tool
```

`202 Accepted`:

```json
{
  "message": "Akun dibuat. Kode verifikasi dikirim ke email Anda.",
  "data": {
    "email": "budi@sekarya.test",
    "status": "pending_verification",
    "gender": "prefer_not_to_say",
    "city": "Jakarta",
    "province": "DKI Jakarta",
    "code_expires_in_minutes": 15,
    "next_step": "POST /api/v1/auth/verify-email"
  }
}
```

**Yang perlu diperhatikan**

- **Tidak ada token di respons ini.** Itu inti langkahnya — mengembalikan token di sini
  akan membuat verifikasi email jadi tanpa arti.
- `status` = `pending_verification`. Akun belum bisa apa-apa.
- `gender`, `city`, dan `province` **opsional** dan dipantulkan kembali apa adanya
  (`null` bila tidak diisi). Dipantulkan karena di titik ini belum ada token, jadi
  `GET /me` belum bisa dipakai untuk memastikan apa yang tersimpan.
- `gender` menerima `male`, `female`, atau `prefer_not_to_say`. Tidak mengirimnya —
  atau mengirim string kosong — tersimpan `null`, dan `null` **bukan** hal yang sama
  dengan `prefer_not_to_say`: `null` berarti belum pernah ditanya, sedangkan
  `prefer_not_to_say` berarti sudah ditanya dan menolak menjawab. Hanya yang `null`
  dianggap identitasnya belum lengkap.
- Wajib: `first_name`, `email`, `password` (+`password_confirmation`).
  Opsional: `last_name`, `username` (huruf/angka/titik/garis bawah, unik),
  `phone` (boleh kosong; format `+628…`, unik bila diisi), `city`, `province`.
- `name` lama masih diterima sebagai alias (dipecah jadi depan/belakang) agar
  klien lama tidak putus — klien baru wajib kirim `first_name`.
- Kolom `name` di database disinkron dari depan+belakang, jadi seluruh
  pembaca lama (profil, notifikasi, admin) tidak berubah.
- Kata sandi diperiksa terhadap **basis data kebocoran publik**; kata sandi yang pernah
  bocor ditolak `422` walaupun panjangnya cukup.

### 1a. Pra-cek ketersediaan (dipakai langkah 1 form daftar)

```bash
curl -s -X POST "$BASE/auth/check-availability" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"email":"budi@sekarya.test","username":"budi.prasetyo","phone":"+628111222333"}' \
  -w '\nstatus=%{http_code}\n'
```

`200 OK` bila semuanya bebas dipakai:

```json
{ "message": "Data tersedia untuk dipakai.", "data": { "available": true } }
```

Bila ada yang sudah dipakai → `422` dengan `errors` per field (bentuk sama
seperti register), mis. `{"message":"...","errors":{"email":["Email sudah terdaftar. ..."]}}`.
Minimal satu dari `email`/`username`/`phone` harus diisi; field yang dikosongkan
tidak ikut dinilai. Aturan uniknya cermin register, jadi hasil pra-cek sama
dengan hasil validasi register.

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

#### `option_responses` adalah DAFTAR, bukan peta

Jawaban atas `options` task memakai bentuk yang sama persis dengan `options`
itu sendiri — **daftar objek `{label, value}`**:

```json
{ "amount": 220000, "option_responses": [{ "label": "Bawa alat sendiri", "value": true }] }
```

Dua hal yang gampang salah dibaca, dan keduanya sudah pernah memakan korban:

- **Bukan peta `{"Bawa alat sendiri": true}`.** Klien yang memodelkannya
  sebagai map akan gagal mengurai, dan karena penguraian halaman bersifat
  semua-atau-tidak, satu penawaran saja menjatuhkan SELURUH halaman feed —
  server menjawab `200` tapi layar melapor gagal memuat.
- **Kosong berangkat sebagai `[]`, bukan `{}`.** Dikunci oleh
  `OptionalFieldsTest::test_bid_option_responses_stay_a_json_array_when_empty`.

`value` sengaja bebas tipe (boolean, angka, atau teks) karena isinya ditentukan
pembuat task. Klien bertipe ketat perlu memperlakukannya sebagai primitif bebas,
bukan string.

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

**Jarak pelamar — `distance_km`.** Setiap penawaran di daftar ini membawa jarak (km, 1
desimal) dari lokasi task ke **lokasi kerja** pelamar (`PUT /me/worker` →
`latitude`/`longitude`), dihitung server. `null` bila salah satu titik belum diisi (task
remote, atau pelamar belum menyetel lokasi kerjanya). Hal yang sama ada di
`workers[].distance_km` pada `GET /tasks/{task}`.

- **Hanya pemberi kerja task itu** yang mendapat angkanya. Di jalur lain — `bids/mine`,
  `my_bid`, respons menawar, dan `workers[]` bila yang membuka bukan pemberi kerja —
  kuncinya tetap ada dengan nilai `null`, supaya bentuk respons sama untuk semua orang.
  Jarak pesaing bukan urusan pelamar lain, dan setiap angka jarak adalah satu persamaan
  menuju lokasi kerja seseorang.
- **Koordinat pekerja tidak pernah keluar**, dan dibulatkan ke 3 desimal (±110 m)
  **sebelum** dihitung. Jarak presisi dari beberapa task cukup untuk trilaterasi rumah
  orang; dengan pembulatan ini yang bisa ditemukan paling banter kotak ±110 m — tingkat
  kekaburan yang sama dengan lokasi task sebelum deal.

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

Activity-nya **sudah ada** — satu per pekerja, dibuka bersama penutupan lelang:

```bash
curl -s "$BASE/tasks/$TASK" -H "Authorization: Bearer $AT" -H 'Accept: application/json' \
  | python3 -c 'import json,sys; print("activity:", len(json.load(sys.stdin)["data"].get("activities") or []))'
```

> **Pekerjaannya sudah terbuka sejak deal.**
>
> Menutup lelang membuat satu activity per orang yang diterima dan memindahkan task ke
> `active` — tanpa menunggu uang. Yang ditahan uang adalah **mulai bekerja**:
> `POST /activities/{activity}/start` menolak dengan `payment_not_held` selama tagihannya
> belum `held`.
>
> **SEMENTARA**, dengan `SEKARYA_PAYMENT_GATE=false` (bawaan hari ini), pemeriksaan itu
> pun dilewati — mekanisme pembayaran belum dikembangkan, dan menuntut `held` berarti
> tidak ada pekerjaan yang pernah bisa dimulai. Tagihannya tetap `pending`
> (`activities[].payment.status` ikut `pending`, bukan `held`), persetujuan hasil tidak
> memindahkannya ke `released`, dan upahnya **tidak** dikreditkan ke saldo pekerja:
> saldo yang lahir tanpa uang bisa ditarik lewat `POST /me/wallet/withdrawals`.
>
> Dua langkah di bawah ini adalah alur yang berlaku lagi begitu
> `SEKARYA_PAYMENT_GATE=true`. Ia tidak membusuk selagi dilewati: seluruh suite test
> berjalan dengan gerbangnya HIDUP.

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

Pekerjaan tetap belum boleh dimulai, karena yang baru ada adalah **pernyataan** bahwa
uangnya dikirim. Yang menyatakan uangnya benar-benar **diterima** adalah orang yang melihat mutasi
rekening — pengelola:

```bash
# 2. Pengelola: mutasi cocok -> dana ditahan -> pekerjaan boleh dimulai
export PAY=<ulid tagihan, dari respons di atas>
curl -s -X POST "$BASE/admin/payments/$PAY/confirm" \
  -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json' | python3 -m json.tool
```

Sesudah itu barulah pekerjaannya boleh dimulai. Daftarnya sendiri sudah ada sejak deal —
satu **per pekerja** yang diterima:

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
> webhook, bukan tangan pengelola. Yang tidak akan berubah: **pekerjaan hanya boleh
> DIMULAI ketika dana benar-benar ditahan**, dan yang menyatakannya bukan pihak yang
> membayar.
> Selama aturan itu dipegang, seluruh alur bisa dibangun dan diuji tanpa gateway sama
> sekali.

Melapor lagi setelah dana ditahan ditolak `422 invalid_status_transition`. Begitu juga
mengonfirmasi tagihan yang belum pernah dilaporkan — **tidak ada jalan dari `pending`
langsung ke `held`.**

---

## 8. Kerjakan dan selesaikan

Sebelum bekerja ada perjalanan, dan perjalanan itu **dua langkah dengan dua aktor**:

```bash
export ACT=01M20DM2GTG81MH7QD8GAK1B1D

# 1. penerima kerja: saya berangkat            -> on_the_way, departed_at terisi
curl -s -X POST "$BASE/activities/$ACT/depart" -H "Authorization: Bearer $AT_PEKERJA" -H 'Accept: application/json'

# 2. PEMBERI KERJA: orangnya sudah sampai      -> arrived, arrived_at terisi
curl -s -X POST "$BASE/activities/$ACT/arrived" -H "Authorization: Bearer $AT" -H 'Accept: application/json'
```

Yang melihat orangnya berdiri di depan pintu adalah tuan rumah, bukan orang yang datang.
Kalau pekerja boleh menyatakan sendiri ia tiba, "sudah sampai" berhenti berarti apa pun
dan pemberi kerja tidak punya satu titik pun untuk menyanggah. Karena itu
`open → in_progress` **tidak ada jalannya**: mulai bekerja hanya dari `arrived`.

Ketiga waktunya disimpan terpisah — `departed_at`, `arrived_at`, `started_at` — karena
selisih di antaranya persis yang ditanyakan saat ada keluhan "kok lama".

```bash
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

**Tag pujian** — opsional, maks 5, tanpa duplikat, dan himpunannya **per arah**:

| Penilai | Tag yang sah |
|---|---|
| pemberi kerja → pekerja | `on_time`, `tidy`, `friendly`, `skilled` |
| pekerja → pemberi kerja | `clear_brief`, `friendly`, `on_time_payment` |

```bash
curl -s -X POST "$BASE/tasks/$TASK/reviews" -H "Authorization: Bearer $AT" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"rating":5,"tags":["on_time","tidy"],"comment":"Rapi dan tepat waktu"}'
```

Tag milik arah lain, tag tak dikenal, atau duplikat → `422 errors.tags.N`. Nilainya kode
mesin, bukan label: teks "Tepat Waktu" urusan aplikasi. `tags` di respons **selalu larik**
(`[]` untuk ulasan tanpa tag dan ulasan lama).

**Daftar ulasan** — penyaring bintang, pencarian komentar, dan pekerjaan yang dinilai:

```bash
curl -s "$BASE/users/<ulid>/reviews?role=worker&rating=5&q=rapi" -H "Authorization: Bearer $AT" -H 'Accept: application/json'
curl -s "$BASE/users/<ulid>/reviews?role=worker&rating_max=2"   -H "Authorization: Bearer $AT" -H 'Accept: application/json'
```

- `rating` (1–5) tepat bintang itu; `rating_max` (1–5) bintang itu ke bawah ("1-2★").
  Keduanya tidak boleh dikirim bersamaan.
- `q` (maks 100 huruf) mencari di **komentar** lewat indeks FULLTEXT `review_search` —
  bukan `LIKE`, dengan normalisasi `SearchTerms` yang sama dengan pencarian nama task
  ("bersih" menemukan "membersihkan"). Tanda baca saja → daftar kosong, bukan semua.
- Setiap ulasan membawa `task: {id, title, category: {slug, name}}` — **tanpa lokasi**:
  daftar ini bisa dibaca siapa pun yang login.

**Ringkasan ulasan** — "★4.9 dari 41", "92% 5 Bintang", angka di setiap chip:

```bash
curl -s "$BASE/users/<ulid>/reviews/summary?role=worker" -H "Authorization: Bearer $AT" -H 'Accept: application/json'
```

```json
{"data": {"rating_avg": 4.9, "rating_count": 41,
          "distribution": {"5": 38, "4": 2, "3": 1, "2": 0, "1": 0}}}
```

`distribution` **selalu objek berkunci "5".."1"** (nol bila kosong). Dihitung dari tabel
`reviews` dengan penyaring yang sama dengan daftarnya, jadi angka di atas layar cocok dengan
baris yang bisa digulir. Tanpa `role` = gabungan dua arah. Persentase ("92% 5 Bintang")
**dihitung klien** dari `distribution`.

**Profil publik satu orang** — dari notifikasi atau tautan:

```bash
curl -s "$BASE/users/<ulid>" -H "Authorization: Bearer $AT" -H 'Accept: application/json'
```

Bentuknya `PublicUser` (sama dengan `poster`, `workers[]`, `bid.bidder`) **plus `skills`**.
Tidak pernah email, nomor HP, alamat, atau koordinat. Akun yang belum verifikasi email,
ditangguhkan, di-ban, atau dihapus dijawab **404 yang sama persis** dengan ULID yang tidak
ada — membedakannya berarti mengonfirmasi orang itu ada dan sedang dimoderasi.

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

### Lokasi tersamar sampai deal

UI menjanjikan *"Detail alamat hanya dibagikan setelah Mitra disetujui"*, jadi server
yang menahannya — klien bisa membaca JSON mentah. Aturannya satu (`Task::revealsLocationTo`)
dan berlaku di **setiap** endpoint yang memuat task: feed, detail, `tasks/worked`,
`bids/mine`, `activities/*`.

| Penonton | `location.text` | Koordinat | `is_precise` |
|---|---|---|---|
| Pemberi kerja task itu | alamat lengkap | penuh | `true` |
| Pekerja yang penawarannya `accepted` di task itu | alamat lengkap | penuh | `true` |
| Siapa pun selainnya — termasuk pelamar `pending`/`rejected` | `null` | dibulatkan 3 desimal (±110 m) | `false` |

```json
"location": {
  "text": null, "area": "Coblong", "city": "Kota Bandung",
  "latitude": -6.892, "longitude": 107.617,
  "is_precise": false, "is_remote": false
}
```

`area` (kecamatan/kelurahan, maks 80 karakter) **selalu** tampil — itu label kartu
"Coblong, Kota Bandung". Diisi pemberi kerja lewat `POST /tasks` / `PUT /tasks/{task}`;
server tidak melakukan geocoding. `distance_km` tetap dihitung dari koordinat asli.
Deal di task **lain** tidak membuka lokasi task ini.

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

Rekening bank tampil **tersamar** — empat digit terakhir, untuk layar "BCA · Ratna Dewi ·
•••• 4910":

```json
{ "type": "bank_account", "bank_code": "BCA", "account_holder_name": "Ratna Dewi",
  "account_number_masked": "•••• 4910" }
```

Empat digit itu disimpan di kolomnya sendiri (`account_number_last4`) saat pengajuan,
jadi daftar ini tidak pernah mendekripsi nomor utuh. Bentuk yang sama ada di
`destination` penarikan (`GET/POST /me/wallet/withdrawals`) dan antrean pencairan
pengelola. Nomor utuh tetap hanya di `GET /admin/verifications/{verification}`, yang
pembacaannya dicatat.

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
| `availability` | 10 | IP |
| `verify-email` | 6 | email + IP |
| `resend-code` | 3 | email |
| `forgot-password` | 3 | email |
| `reset-password` (web) | 6 | email + IP |
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

## 16. Saldo — dompet yang menempel pada akun

Satu orang, satu dompet. Ia bukan kolom di `users`: sebuah kolom saldo menjawab
**berapa** tanpa bisa menjawab **kenapa**, dan pada uang, pertanyaan kedua itulah yang
ditanyakan orang saat angkanya tidak sesuai harapan mereka. Yang ada di bawahnya buku
besar `wallet_entries` — append-only, satu baris per kejadian, masing-masing membawa
saldo sesudahnya.

```
                ┌──────────── isi ulang (dikonfirmasi pengelola)
uang masuk ─────┼──────────── pengembalian dana task yang batal
                └──────────── upah pekerja saat dana task dilepas

uang keluar ───────────────── penarikan ke rekening terverifikasi
```

```bash
curl -s "$BASE/me/wallet" -H "Authorization: Bearer $AT" -H 'Accept: application/json' | jq .data
```

```json
{ "id": null, "balance": 0, "created_at": null }
```

`id: null` bukan galat: **membaca saldo tidak membuat baris dompet.** Orang yang belum
pernah menerima atau mengisi apa pun mendapat bentuk respons yang sama dengan yang sudah,
jadi klien tidak perlu punya dua cabang untuk satu layar. Angkanya juga ikut di `GET /me`
sebagai `wallet.balance`, supaya layar profil cukup satu permintaan.

### Isi saldo — melapor, bukan mengisi

```bash
curl -s -X POST "$BASE/me/wallet/topups" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"amount": 250000, "sender_note": "BCA 1234 a.n. Budi Prasetyo"}' | jq .data
```

```json
{
  "id": "01M2WALLETTOPUP00000000001",
  "amount": 250000,
  "status": "awaiting_confirmation",
  "awaits_confirmation": true,
  "sender_note": "BCA 1234 a.n. Budi Prasetyo",
  "rejection_reason": null
}
```

`201`, dan **saldo masih nol.** Polanya sama persis dengan `POST /tasks/{task}/payment/hold`:
yang Anda kirim adalah laporan, bukan uang. Yang menambah saldo hanya
`POST /admin/wallet/topups/{topup}/confirm`, dipanggil orang yang melihat mutasi rekening.

Kalau panggilan ini menambah saldo, siapa pun bisa mengisi dompetnya sendiri dengan satu
permintaan HTTP — dan menariknya ke rekening sebelum ada yang sempat melihat.

`sender_note` bukan hiasan. Itulah satu-satunya petunjuk yang dipakai pengelola untuk
menemukan transfernya; tanpa itu antreannya hanya bisa diputuskan dengan menebak.

Yang belum diputuskan bisa ditarik kembali, dan itu **tidak** menyentuh saldo — memang
belum ada yang pernah ditambahkan:

```bash
curl -s -X POST "$BASE/me/wallet/topups/$TOPUP/cancel" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' | jq '.data.status'
```

Permintaan yang menggantung dibatasi jumlahnya (bawaan 3, di `config/sekarya.php` →
`wallet.max_pending_requests`). Ini bukan rate limit: rate limit membatasi kecepatan,
sedangkan yang dijaga di sini berapa banyak yang menunggu sekaligus. Tiga per menit
selama sehari tetap lolos rate limit dan tetap meninggalkan ribuan baris yang harus
dibuka pengelola satu per satu — sementara di belakangnya ada pekerja yang menunggu
uangnya.

### Upah — pekerja sebagai penerima bayaran

Tidak ada endpoint untuk ini, dan itu disengaja: upah tidak diklaim, ia jatuh sendiri.
Saat pekerja **terakhir** disetujui (`POST /activities/{activity}/approve`), dana task
dilepas dan setiap pekerja menerima kredit sebesar penawarannya sendiri.

```bash
curl -s "$BASE/me/wallet/entries" -H "Authorization: Bearer $AT" -H 'Accept: application/json' \
  | jq '.data[0]'
```

```json
{
  "type": "earning",
  "direction": "credit",
  "amount": 150000,
  "balance_after": 150000,
  "reference_type": "activities",
  "description": "Upah task #TSK-0007"
}
```

Angkanya **harga per orang**, bukan `agreed_amount` task — yang terakhir total seluruh
pekerja, dan memakainya berarti setiap orang dari task 30 orang menerima seluruh
tagihan. Pelepasannya juga sekali, saat yang terakhir disetujui: melepas pada persetujuan
pertama akan mengeluarkan seluruh tagihan untuk satu orang.

`balance_after` yang membuat riwayat ini terbaca seperti rekening koran alih-alih daftar
angka lepas yang harus dijumlahkan klien. Id internal kejadian penyebabnya tidak ikut
keluar — id berurutan membocorkan volume bisnis, alasan yang sama membuat seluruh rute
memakai ULID.

Yang ikut keluar adalah **task-nya**, supaya baris bisa berjudul "Pindahan Lemari · Dana
ditahan" dan dibuka:

```json
{ "type": "task_hold", "reference_type": "task_fund_movements",
  "task": { "id": "01M20DM1…", "task_number": "TK-260924-AB12CD", "title": "Pindahan Lemari Lantai 2",
            "category": { "slug": "pindahan", "name": "Pindahan & Angkut" } } }
```

`task` terisi untuk `task_hold`/`task_release`, `refund`, dan `earning`; `null` untuk
`topup`, `withdrawal`, `withdrawal_reversal`, dan `adjustment_*`. Task yang sudah dihapus
tetap disebut. Kuerinya tetap per halaman (satu per jenis rujukan + satu untuk task),
bukan per baris.

### Ringkasan — total dijumlahkan server

"Total Masuk / Total Keluar" di Riwayat dan "Pendapatan minggu ini" di Beranda Mitra
**tidak boleh** dijumlahkan dari halaman `entries` di klien: daftarnya bercursor tanpa
`total`, jadi angkanya berubah setiap kali pengguna menggulir.

```bash
curl -s "$BASE/me/wallet/summary?from=2026-09-01T00:00:00%2B07:00&to=2026-10-01T00:00:00%2B07:00&compare_previous=1&group=month" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' | jq .data
```

```json
{
  "from": "2026-09-01T00:00:00+07:00", "to": "2026-10-01T00:00:00+07:00",
  "credit_total": 550000, "debit_total": 230000, "entries_count": 4,
  "by_type": { "topup": 250000, "refund": 300000, "earning": 0, "task_hold": 230000,
               "task_release": 0, "withdrawal": 0, "withdrawal_reversal": 0,
               "adjustment_credit": 0, "adjustment_debit": 0 },
  "earning_total": 0,
  "previous": { "credit_total": 480000, "earning_total": 120000 },
  "by_month": [
    { "month": "2026-09", "credit_total": 550000, "debit_total": 230000,
      "entries_count": 4, "earning_total": 0 }
  ]
}
```

- `from` inklusif, `to` eksklusif — persis penyaring `entries`, jadi totalnya
  menjumlahkan baris yang sama. Keduanya **berpasangan**; tanpa keduanya rentangnya bulan
  kalender berjalan (zona aplikasi). Paling panjang 366 hari.
- `by_type` selalu memuat setiap jenis (nol bila kosong), selalu objek.
- `earning_total` = jumlah mutasi `earning` dalam rentang — angka "pendapatan".
  "Pendapatan minggu ini vs pekan lalu": dua panggilan dengan rentang minggu berbeda,
  atau `compare_previous=1` → `previous` (periode sepanjang sama, tepat sebelum `from`).
  "+18% dari pekan lalu" dihitung klien dari dua angka ini.
- `types[]`/`direction` menyaring agar total mengikuti tab yang dibuka. `group=month`
  menambahkan deret `by_month` (bucket memakai offset `from`).
- Selalu milik yang login — tidak ada parameter pemilik. Tidak membuat dompet.
- `SUM … GROUP BY type` pada indeks `(wallet_id, created_at, id)`; `EXPLAIN` diperiksa
  di test pada 3.000 baris.

### Pengembalian dana — task yang batal

Task yang dibatalkan setelah dananya ditahan mengembalikan uang itu **ke saldo
pembayarnya**, sebagai baris `refund`. Bukan ke rekening: transfer balik menuntut antrean
manual ketiga dan menahan uang orang selama antrean itu berjalan, padahal hampir semua
pembatalan diikuti task pengganti. Yang menginginkan uangnya di bank memakai pintu yang
sama dengan pekerja — penarikan.

Yang menerima selalu `payment.payer_id`, bahkan ketika yang membatalkan adalah pekerjanya.
Mengembalikan ke pembatal akan memindahkan uang pemberi kerja ke orang lain.

### Penarikan — satu pintu keluar

```bash
curl -s -X POST "$BASE/me/wallet/withdrawals" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"amount": 200000}' | jq .data
```

```json
{
  "id": "01M2WALLETWD00000000000001",
  "amount": 200000,
  "status": "requested",
  "awaits_processing": true,
  "destination": { "bank_code": "BCA", "account_holder_name": "Budi Prasetyo" }
}
```

Dua hal yang harus dibaca benar:

**Rekening tujuan tidak dikirim klien.** Ia diambil dari verifikasi rekening yang sudah
disetujui pengelola. Kalau tujuannya bisa dikirim per permintaan, persetujuan rekening
berhenti berarti apa pun: saldo hasil kerja bisa dialirkan ke rekening mana saja yang
belum pernah dicocokkan dengan identitas pemiliknya. Tanpa satu pun rekening
terverifikasi, `422 bank_account_not_verified` — dan verifikasi **identitas** tidak
menggantikannya, keduanya jenis pengajuan yang berbeda (bagian 13).

**Saldonya langsung berkurang di sini**, bukan saat pengelola mencairkan:

```bash
curl -s "$BASE/me/wallet" -H "Authorization: Bearer $AT" -H 'Accept: application/json' | jq '.data.balance'
```

Kalau pemotongan menunggu pencairan, saldo yang sama bisa diminta berkali-kali selama
antrean pengelola belum tersentuh — tiga permintaan dua ratus ribu atas saldo dua ratus
ribu akan lolos semuanya, dan ketiganya terlihat sah saat dibuka satu per satu.

Konsekuensinya berlaku ke arah sebaliknya: **penolakan dan pembatalan mengembalikan dana
itu**, sebagai baris `withdrawal_reversal`. Buku besarnya append-only, jadi yang muncul
baris baru — bukan baris lama yang dihapus.

```bash
curl -s -X POST "$BASE/me/wallet/withdrawals/$WD/cancel" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' | jq '.data.status'
```

Saldo kurang menyebut kekurangannya, supaya klien tidak perlu menghitung sendiri:

```json
{
  "message": "Saldo tidak cukup. Tersedia Rp60.000, diminta Rp100.000.",
  "code": "insufficient_balance",
  "context": { "balance": 60000, "requested": 100000, "shortfall": 40000 }
}
```

### Sisi pengelola

Dua antrean, keduanya menyentuh uang sungguhan.

```bash
curl -s "$BASE/admin/wallet/topups" -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json'
curl -s -X POST "$BASE/admin/wallet/topups/$TOPUP/confirm" \
  -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json'
```

Konfirmasi itu **satu-satunya jalan saldo bisa bertambah dari isi ulang**, sederajat
dengan `payments/{payment}/confirm` sebagai satu-satunya jalan menuju `held`. Menekan dua
kali tidak menggandakan uang: kunci baris menahannya di dalam transaksi, dan indeks
unique `(reference_type, reference_id, type)` di `wallet_entries` menahannya di basis
data. Penolakannya **final** — pengajuan ulang membuat baris baru, dan riwayat penolakan
tetap utuh sebagai sinyal.

```bash
curl -s "$BASE/admin/wallet/withdrawals" -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json'
curl -s -X POST "$BASE/admin/wallet/withdrawals/$WD/complete" \
  -H "Authorization: Bearer $ADMIN_AT" -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"transfer_reference": "TRX-99887766"}'
```

`complete` **tidak memotong saldo lagi** — saldonya sudah berkurang sejak penarikan
diminta; memotongnya di sini berarti penggunanya membayar dua kali untuk satu pencairan.
`reject` yang mengembalikannya.

**Nomor rekening tidak keluar di antrean pencairan.** Ia terbaca satu layar lebih jauh, di
`GET /admin/verifications/{verification}` lewat `destination.verification_id`, dan
pembacaan di sana dicatat sebagai `verification.viewed`. Pola yang sama dengan antrean
verifikasi dan NIK: daftar antreannya secara harfiah tidak punya kode untuk
mengeluarkannya.

Keempat tindakan itu tercatat di `admin_audit_logs` sebagai `wallet_topup.confirmed`,
`wallet_topup.rejected`, `wallet_withdrawal.completed`, dan `wallet_withdrawal.rejected`.

---

## Ringkasan endpoint

**116 endpoint, satu baris masing-masing.** Daftar ini dibangkitkan dari
`php artisan route:list`, dan sebuah test menjaganya tetap seiring: menambah rute tanpa
mendaftarkannya di `docs/openapi.yaml` membuat suite gagal
(`tests/Feature/Docs/ApiDocumentationTest.php`).

Semua di bawah `/api/v1`. Kolom **Token**: `access` = token pendek 8 jam, `long_lived` =
token 30 hari yang HANYA bisa refresh, `admin` = token pengelola, `—` = tanpa token.
Kolom **Limit** menyebut pembatas laju yang berlaku; angkanya di `config/sekarya.php`.

> [!important] 57 endpoint pertama untuk PENGGUNA, 33 terakhir untuk PENGELOLA, dan
> tokennya **tidak bisa ditukar**. Akun pengelola ada di tabelnya sendiri dengan
> guard-nya sendiri: token pengguna di `/admin` menghasilkan `401`, dan token pengelola
> di endpoint pengguna juga `401`. Lihat bagian **Pengelola** di bawah.

**Auth — tanpa token, kecuali dua terakhir**

| | Endpoint | Token | Limit | Keterangan |
|---|---|---|---|---|
| `POST` | `/auth/login` | — | `login` | Masuk via **tepat satu** dari email, username, atau nomor HP (`08…`/`+62…`). Balasan sama untuk kata sandi salah maupun identitas tak dikenal. |
| `POST` | `/auth/logout` | kedua token | `api` | Keluar. Mencabut **kedua** token, termasuk yang berumur panjang. |
| `POST` | `/auth/refresh` | long_lived | `refresh` | Tukar `long_lived` jadi `access` baru. Access token lama langsung mati. |
| `POST` | `/auth/register` | — | `register` | Daftar akun. `202`, **tanpa token** — akun belum aktif. |
| `POST` | `/auth/check-availability` | — | `availability` | Pra-cek unik email/username/phone. `200` bila bebas, `422` per field bila dipakai. |
| `POST` | `/auth/resend-code` | — | `resend` | Kirim ulang kode. Balasan sama untuk email dikenal maupun tidak. |
| `POST` | `/auth/forgot-password` | — | `forgot` | Kirim tautan reset sekali pakai. `422 email_not_registered` bila email tidak terdaftar. |
| `POST` | `/auth/verify-email` | — | `verify` | Masukkan kode dari email. Satu-satunya jalan ke `active` + pasangan token. |

**Katalog & akun**

| | Endpoint | Token | Limit | Keterangan |
|---|---|---|---|---|
| `GET` | `/cities` | — | `api` | Master kabupaten/kota (B12). **Publik**; `q` awalan nama, `limit` ≤100. |
| `GET` | `/categories` | access | `api` | Katalog kategori + harga referensi. `?city=` memakai acuan per kota bila sampel cukup, kalau tidak nasional. |
| `GET` | `/me` | access | `api` | Profil sendiri, lengkap dengan data kontak. |
| `PATCH` | `/me` | access | `api` | Ubah profil. `extras` divalidasi per peran. `phone`: aturan sama dengan pendaftaran, unik kecuali milik sendiri; nomor yang berubah membuat `phone_verified` kembali `false`. |
| `GET` | `/me/worker` | access | `api` | Profil pekerja sendiri. Membacanya tidak membuat baris. |
| `PUT` | `/me/worker` | access | `api` | Isi/ubah profil pekerja. `null` = kembali ikut akun. `is_available` (ketersediaan) dan `headline` (profesi mitra) ikut di sini. |
| `POST` | `/me/worker/redeem` | access | `write` | Tukar kode undangan mitra menjadi baris `user_workers` + `active_mode = working`. |
| `GET` | `/me/worker/invite-availability` | access | `api` | Sinyal ketersediaan kode undangan di kota/provinsi (boolean saja). |
| `GET` | `/me/verifications` | access | `api` | Status verifikasi identitas. Hanya status, bukan artefaknya. Rekening: `account_number_masked` ("•••• 4910"), tidak pernah nomor utuh. |
| `POST` | `/me/verifications` | access | `api` | Ajukan verifikasi identitas (KTP, selfie, rekening). |
| `POST` | `/me/devices` | access | `write` | Daftarkan token perangkat FCM untuk push. Token sama = berpindah pemilik. |
| `DELETE` | `/me/devices/{token}` | access | `api` | Lepaskan token perangkat saat logout. Idempoten. |
| `GET` | `/me/notifications` | access | `api` | Kotak masuk notifikasi sendiri — riwayat yang sama dengan push FCM. `unread=1` menyaring yang belum dibaca. Cursor, terbaru dulu. |
| `GET` | `/me/notifications/unread-count` | access | `api` | Jumlah belum dibaca untuk badge lonceng, dihitung server. Balasan `{count}`. |
| `POST` | `/me/notifications/read-all` | access | `api` | Tandai seluruh kotak masuk sudah dibaca. Balasan `{count}` (sisa belum dibaca = 0). Idempoten. |
| `POST` | `/me/notifications/{notification}/read` | access | `api` | Tandai satu notifikasi sudah dibaca. Milik orang lain dijawab 404 yang sama dengan id yang tidak ada. |
| `GET` | `/me/address` | access | `api` | Alamat tersimpan sendiri. `{"data": null}` (200) bila belum pernah diisi. Hanya pemiliknya. |
| `PUT` | `/me/address` | access | `api` | Simpan/ganti alamat tersimpan (ganti utuh). Koordinat wajib berpasangan. |
| `DELETE` | `/me/address` | access | `api` | Hapus alamat tersimpan. Idempoten (204). |
| `GET` | `/skills` | access | `api` | Katalog keahlian. |
| `GET` | `/workers` | access | `api` | Daftar pekerja. Filter: `city`, `province`, `gender`, `ready_to_work`, `available`. Cursor. |
| `POST` | `/uploads` | access | `write` | Unggah gambar (foto task, avatar, bukti kerja). `purpose=proof` menyimpan ke `uploads/proofs` dan menandai pemiliknya. Maks 10 MB; balasannya `path`. |

**Task**

| | Endpoint | Token | Limit | Keterangan |
|---|---|---|---|---|
| `GET` | `/tasks` | access | `api` | Feed siap dilamar. Filter: `q`, `lat`/`lng`/`radius_km`, `posted_within_hours`, `needed_from`/`needed_to` (jadwal, `from` inklusif `to` eksklusif), `category_id`/`category_ids[]`, `city`, `budget_from`/`budget_to`, `skills`, `match_my_skills`, `exclude_my_bids`. |
| `POST` | `/tasks` | access | `write` | Buat task. `workers_needed` menentukan berapa orang direkrut. `publish_now` mengabari mitra sekitar → `meta.notified_workers` (B13). |
| `GET` | `/tasks/posted` | access | `api` | Task yang saya posting. |
| `GET` | `/tasks/worked` | access | `api` | Task yang saya kerjakan. |
| `GET` | `/tasks/{task}` | access | `api` | Detail satu task, termasuk `hiring`, `workers`, `payment`, `activities`, `cancel_request`. Alamat & koordinat penuh hanya untuk pemberi kerja dan pekerja yang sudah deal (`location.is_precise`). |
| `PUT` | `/tasks/{task}` | access | `write` | Sunting isi task. Parsial; hanya `draft`/`open`. |
| `POST` | `/tasks/{task}/cancel` | access | `api` | Batalkan LANGSUNG — hanya bila belum ada pekerja yang deal. |
| `POST` | `/tasks/{task}/cancel-requests` | access | `write` | Minta persetujuan pembatalan ke pekerja (sudah deal). |
| `GET` | `/tasks/{task}/cancel-request` | access | `api` | Baca permintaan pembatalan yang menunggu. |
| `POST` | `/tasks/{task}/cancel-requests/{cancelRequest}/approve` | access | `api` | Pekerja menyetujui — task batal atas nama pemberi kerja. |
| `POST` | `/tasks/{task}/cancel-requests/{cancelRequest}/reject` | access | `api` | Pekerja menolak — task jalan terus. |
| `POST` | `/tasks/{task}/cancel-requests/{cancelRequest}/withdraw` | access | `api` | Pemberi kerja menarik permintaannya yang masih menunggu. |
| `POST` | `/tasks/{task}/publish` | access | `api` | `draft` -> `open`. Lelang dibuka. |
| `POST` | `/tasks/{task}/start` | access | `api` | Berhenti merekrut lebih awal: target turun ke jumlah yang sudah diterima. |
| `POST` | `/tasks/{task}/approve-all` | access | `api` | Konfirmasi selesai & rilis dana untuk semua pekerja sekali tekan. Belum ada hasil diserahkan → `422 no_submitted_activities`. |
| `GET` | `/tasks/posted/counts` | access | `api` | Hitungan task saya per status (judul tab). Setiap status selalu ada. |
| `GET` | `/tasks/worked/counts` | access | `api` | Hitungan task yang saya kerjakan per status. |
| `GET` | `/tasks/bookmarked` | access | `api` | Tugas yang saya simpan. Cursor. Tiap baris membawa `is_bookmarked`. |
| `GET` | `/tasks/{task}/contacts` | access | `api` | Nomor kontak peserta setelah deal (B17). Peserta task saja; belum deal → `422`. |
| `PUT` | `/tasks/{task}/bookmark` | access | `api` | Simpan tugas. Idempoten (204). |
| `DELETE` | `/tasks/{task}/bookmark` | access | `api` | Lepas simpanan. Idempoten (204). |

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

**Saldo**

| | Endpoint | Token | Limit | Keterangan |
|---|---|---|---|---|
| `GET` | `/me/wallet` | access | `api` | Saldo sendiri. Membacanya tidak membuat baris dompet. |
| `GET` | `/me/wallet/entries` | access | `api` | Riwayat mutasi. Filter: `type`, `types[]` (beberapa jenis), `direction`, `q` (kata di `description`), `from`/`to` (ISO-8601 beroffset; `from` inklusif, `to` eksklusif), `min_amount`/`max_amount`. Tiap baris membawa `task: {id, task_number, title, category}` atau `null`. Cursor. |
| `GET` | `/me/wallet/summary` | access | `api` | Ringkasan dijumlahkan server: `credit_total`, `debit_total`, `entries_count`, `by_type`, `earning_total`. `types[]`/`direction` menyaring; `compare_previous=1` menambah `previous`; `group=month` menambah `by_month`. `from`/`to` berpasangan (bawaan: bulan berjalan), maks 366 hari. |
| `GET` | `/me/wallet/config` | access | `api` | Rekening tujuan isi saldo (dari env; kosong = tak ditampilkan) + `limits` (`min_topup`, `max_topup`, `min_withdrawal`, `max_withdrawal`, `max_pending_requests`). |
| `GET` | `/me/wallet/topups` | access | `api` | Permintaan isi saldo saya. Filter: `status`. |
| `POST` | `/me/wallet/topups` | access | `write` | **Lapor** sudah transfer untuk isi saldo. **Tidak** menambah saldo. Balasan membawa `unique_code` (3 digit) + `transfer_amount` (= jumlah + kode) untuk pencocokan mutasi. |
| `POST` | `/me/wallet/topups/{topup}/cancel` | access | `api` | Batalkan permintaan yang belum diputuskan. Saldo tidak tersentuh. |
| `GET` | `/me/wallet/withdrawals` | access | `api` | Permintaan penarikan saya. Filter: `status`. |
| `POST` | `/me/wallet/withdrawals` | access | `write` | Tarik saldo ke rekening terverifikasi. **Saldo langsung berkurang.** |
| `POST` | `/me/wallet/withdrawals/{withdrawal}/cancel` | access | `api` | Batalkan; dana yang ditahan **dikembalikan**. |

**Pengerjaan**

| | Endpoint | Token | Limit | Keterangan |
|---|---|---|---|---|
| `GET` | `/activities/mine` | access | `api` | Pekerjaan yang saya kerjakan. |
| `GET` | `/activities/{activity}` | access | `api` | Detail satu activity. |
| `POST` | `/activities/{activity}/approve` | access | `api` | Setujui hasil. Dana dilepas saat pekerja **terakhir** disetujui. |
| `POST` | `/activities/{activity}/arrived` | access | `api` | **Pemberi kerja** mengakui pekerjanya sudah sampai. |
| `POST` | `/activities/{activity}/depart` | access | `api` | Pekerja berangkat ke lokasi. |
| `POST` | `/activities/{activity}/reject` | access | `api` | Tolak hasil. Task jadi `disputed`, dana tetap ditahan. |
| `POST` | `/activities/{activity}/start` | access | `api` | Pekerja mulai bekerja. Hanya dari `arrived`. |
| `POST` | `/activities/{activity}/submit` | access | `api` | Serahkan hasil + bukti foto. Foto **wajib** (jumlah minimum dari `config/sekarya.php`), dan path harus diunggah sendiri lewat `POST /uploads` (`purpose=proof`). |
| `POST` | `/activities/{activity}/location` | access | `write` | Bagikan lokasi langsung selama `on_the_way` (B8). Balasan + `live: {distance_km, eta_minutes, updated_at}`. |
| `POST` | `/activities/{activity}/updates` | access | `write` | Tulis catatan kemajuan pekerja (B9). Tampil sebagai `latest_update`. |
| `PUT` | `/activities/{activity}/checklist` | access | `api` | Centang checklist pekerjaan (B10). `state` harus sepanjang `tasks.checklist`; beda → `422 checklist_state_mismatch`. |

**Penilaian**

| | Endpoint | Token | Limit | Keterangan |
|---|---|---|---|---|
| `POST` | `/tasks/{task}/reviews` | access | `api` | Beri penilaian (+ `tags` opsional, per arah). Pemberi kerja menyebut `worker_id` bila pekerjanya banyak. |
| `GET` | `/users/{user}/reviews` | access | `api` | Penilaian yang diterima seseorang. `role`, `rating`/`rating_max`, `q` (komentar), `has_photos` (chip "Dengan Foto"). Tiap baris membawa `tags` + `photos[]`/`has_photos` + `task`. |
| `GET` | `/users/{user}/reviews/summary` | access | `api` | Rata-rata, jumlah, sebaran bintang "5".."1". Persentase dihitung klien. `role` opsional. |
| `GET` | `/users/{user}` | access | `api` | Profil publik satu orang (+`skills`). Akun tidak aktif → 404. |

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
| `POST` | `/admin/payments/{payment}/confirm` | admin | `admin` | **Satu-satunya jalan ke `held`** -> pekerjaan boleh dimulai. |
| `POST` | `/admin/payments/{payment}/reject` | admin | `admin` | Dana tidak ditemukan. Kembali ke `pending`, `reason` dibaca pemberi kerja. |
| `GET` | `/admin/wallet/topups` | admin | `admin` | Antrean isi saldo. Bawaannya `awaiting_confirmation`, paling lama menunggu di depan. |
| `POST` | `/admin/wallet/topups/{topup}/confirm` | admin | `admin` | **Satu-satunya jalan saldo bertambah dari isi ulang.** |
| `POST` | `/admin/wallet/topups/{topup}/reject` | admin | `admin` | Dana tidak ditemukan. Final; `reason` dibaca penggunanya. |
| `GET` | `/admin/wallet/withdrawals` | admin | `admin` | Antrean pencairan. Nomor rekening **tidak** keluar di sini. |
| `POST` | `/admin/wallet/withdrawals/{withdrawal}/complete` | admin | `admin` | Transfer sudah dikirim. **Tidak** memotong saldo lagi. |
| `POST` | `/admin/wallet/withdrawals/{withdrawal}/reject` | admin | `admin` | Ditolak; dana yang ditahan **dikembalikan**. `reason` wajib. |
| `GET` | `/admin/users` | admin | `admin` | Daftar pengguna. Filter `status`, `email` (**pencocokan persis**, bukan pencarian). |
| `GET` | `/admin/users/{user}` | admin | `admin` | Detail satu pengguna. |
| `POST` | `/admin/users/{user}/suspend` | admin | `admin` | Tangguhkan **dan cabut seluruh tokennya**. `reason` wajib. |
| `POST` | `/admin/users/{user}/ban` | admin | `admin` | Blokir **dan cabut seluruh tokennya**. `reason` wajib. |
| `POST` | `/admin/users/{user}/reinstate` | admin | `admin` | Pulihkan. Akun yang belum verifikasi email kembali ke `pending_verification`. |
| `GET` | `/admin/admins` | admin | `admin` | Daftar akun pengelola. **Hanya `super_admin`.** |
| `POST` | `/admin/admins` | admin | `admin` | Buat pengelola baru. **Hanya `super_admin`.** Perannya dipaksa `admin`. |
| `GET` | `/admin/admins/{admin}` | admin | `admin` | Detail satu akun pengelola. **Hanya `super_admin`.** |
| `DELETE` | `/admin/admins/{admin}` | admin | `admin` | Hapus pengelola. **Hanya `super_admin`**, dan `super_admin` tidak bisa dihapus. |
| `GET` | `/admin/worker-invite-codes` | admin | `admin` | Daftar kode undangan mitra. |
| `POST` | `/admin/worker-invite-codes` | admin | `admin` | Buat kode undangan mitra. |
| `GET` | `/admin/worker-invite-codes/{code}` | admin | `admin` | Detail satu kode undangan beserta kuotanya. |
| `POST` | `/admin/worker-invite-codes/{code}/deactivate` | admin | `admin` | Nonaktifkan kode yang bocor; redeem berhenti. |
| `GET` | `/admin/worker-invite-codes/{code}/redemptions` | admin | `admin` | Siapa saja yang memakai kode ini. |

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
| `task_not_editable` | 422 | Isi task tidak bisa diubah lagi (bukan `draft`/`open`) |
| `workers_needed_below_hired` | 422 | Target pekerja diturunkan di bawah yang sudah diterima |
| `cancel_request_pending` | 422 | Sudah ada permintaan pembatalan yang menunggu jawaban |
| `no_pending_cancel_request` | 422 | Tidak ada permintaan yang menggantung — sudah dijawab atau ditarik |
| `not_cancel_responder` | 403 | Bukan pekerja yang dimintai persetujuan pada permintaan ini |
| `review_not_allowed` | 422 | Belum selesai, atau sudah menilai |
| `review_tag_not_allowed` | 422 | Tag milik arah penilaian lain (hanya dari luar HTTP; lewat HTTP ditolak lebih dulu sebagai `errors.tags.N`) |
| `admin_access_denied` | 403 | Pengelola dinonaktifkan, atau perannya tidak mencakup tindakan itu (`context.reason`) |
| `super_admin_protected` | 403 | `super_admin` tidak bisa dihapus maupun dinonaktifkan |
| `insufficient_balance` | 422 | Saldo kurang. `context` menyebut `balance`, `requested`, `shortfall` |
| `bank_account_not_verified` | 422 | Menarik saldo tanpa rekening yang disetujui pengelola |
| `wallet_request_not_pending` | 422 | Permintaan saldo sudah diputuskan; tidak bisa diubah lagi |
| `too_many_pending_wallet_requests` | 422 | Terlalu banyak permintaan saldo menggantung sekaligus |

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
bash docs/smoke.sh                   # 194 pemeriksaan
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
  komisi, pelepasan otomatis, dan refund sebagian belum ada. Yang **sudah** ada sejak
  saldo: dana dilepas ke dompet pekerja, pengembalian dana task batal masuk ke dompet
  pembayarnya, dan pencairan ke rekening lewat antrean pengelola (bagian 16).
- **Isi saldo & pencairan otomatis.** Keduanya transfer manual yang dicocokkan pengelola
  di mutasi rekening. Tidak ada virtual account, tidak ada disbursement API.
- **Penyelesaian sengketa.** `reject` membuat task `disputed` dan dana tetap ditahan;
  belum ada jalan keluar dari status itu lewat API.
- **Chat.** Diputuskan memakai database terpisah; kaitkan lewat `tasks.ulid`.
- **Notifikasi**, alamat tersimpan, urut berdasarkan jarak, dan penutup lelang otomatis
  (task yang `bidding_closes_at`-nya lewat disembunyikan dari feed, tapi statusnya tetap
  `open` sampai ada job yang mengubahnya).
