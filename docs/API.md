# Sekarya API — Panduan

Semua yang ada di dokumen ini dijalankan terhadap kode ini, bukan disusun dari ingatan.

| | |
|---|---|
| **Base URL** | `http://127.0.0.1:8000/api/v1` |
| **Kontrak mesin** | [`docs/openapi.yaml`](openapi.yaml) — OpenAPI 3.1, lint bersih, 34 operation cocok dengan 34 rute nyata |
| **Uji otomatis** | `bash docs/smoke.sh` — 66 pemeriksaan |
| **Database** | MySQL 8+ / InnoDB |
| **Wajib di setiap request** | `Accept: application/json` — tanpa ini Laravel bisa membalas HTML |

Kalau dokumen ini dan spec berselisih, **spec plus `php artisan test` yang benar.**

---

## Cara tercepat memastikan semuanya jalan

```bash
bash docs/smoke.sh
```

Menjalankan server sendiri, mereset database, mendaftar akun lewat alur auth yang
sebenarnya, menjalankan 66 pemeriksaan, lalu membereskan diri. Keluaran akhir:

```
SEMUA LULUS  66/66 pemeriksaan
```

Kalau mau memakai server yang sudah jalan: `bash docs/smoke.sh 8000`.

> [!warning] Smoke test menjalankan `migrate:fresh --seed` — seluruh data dev hilang.

Sisa dokumen ini untuk mencoba manual.

---

## 0. Persiapan

```bash
# sekali saja
mysql -u root -e "CREATE DATABASE IF NOT EXISTS sekarya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

php artisan migrate:fresh --seed     # 14 tabel + 9 kategori + 42 keahlian
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
    "name":"Budi Prasetyo",
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

Sekarang transfer — inilah gerbangnya:

```bash
curl -s -X POST "$BASE/tasks/$TASK/payment/hold" \
  -H "Authorization: Bearer $AT" -H 'Accept: application/json' -w '\nstatus=%{http_code}\n' | python3 -m json.tool
```

`201`. Balasannya **daftar** — satu activity per pekerja yang diterima. Task satu orang
memakai bentuk yang sama, berisi satu elemen, supaya klien tidak perlu dua jalur
pembacaan untuk hal yang sama.

```json
{
  "data": [
    {
      "id": "01M20DM2GTG81MH7QD8GAK1B1D",
      "status": "open",
      "agreed_amount": 120000,
      "opened_at": "2026-09-08T10:04:00+00:00",
      "payment": { "status": "held", "is_held": true },
      "task": { "status": "active" }
    },
    { "id": "01M20DM2GX7Q3Z0A9WQ4T8N2KC", "status": "open", "agreed_amount": 150000 }
  ]
}
```

`agreed_amount` di sini adalah harga **per orang**, dari penawarannya sendiri — bukan
total task. Tagihannya satu baris sebesar jumlah semuanya, karena pemberi kerja
mentransfer sekali.

Perekrutan harus **selesai** dulu (task `dealt`). Selama masih ada slot kosong, endpoint
ini menolak — kalau tidak, pekerja yang direkrut belakangan tidak akan pernah punya
activity.

> [!important] Ini **stub**
> Mekanisme pembayaran belum diriset, jadi belum ada gateway. Nanti pemicunya webhook,
> bukan endpoint ini. Yang tidak akan berubah: **activity hanya boleh terbuka ketika dana
> benar-benar ditahan.** Selama aturan itu dipegang, seluruh alur bisa dibangun dan diuji
> tanpa gateway sama sekali.

Transfer dua kali ditolak `422 invalid_status_transition`.

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

## 12. Verifikasi identitas

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
dipakai beberapa akun, dan **terenkripsi** untuk dibaca manusia saat sengketa. Tidak
pernah dalam bentuk mentah.

---

## 13. Rate limit & CORS

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

## Ringkasan endpoint

**35 endpoint, satu baris masing-masing.** Daftar ini dibangkitkan dari
`php artisan route:list`, dan sebuah test menjaganya tetap seiring: menambah rute tanpa
mendaftarkannya di `docs/openapi.yaml` membuat suite gagal
(`tests/Feature/Docs/ApiDocumentationTest.php`).

Semua di bawah `/api/v1`. Kolom **Token**: `access` = token pendek 8 jam, `long_lived` =
token 30 hari yang HANYA bisa refresh, `—` = tanpa token. Kolom **Limit** menyebut
pembatas laju yang berlaku; angkanya di `config/sekarya.php`.

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
| `GET` | `/me/verifications` | access | `api` | Status verifikasi identitas. Hanya status, bukan artefaknya. |
| `POST` | `/me/verifications` | access | `api` | Ajukan verifikasi identitas (KTP, selfie, rekening). |
| `GET` | `/skills` | access | `api` | Katalog keahlian. |

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
| `GET` | `/tasks/{task}/payment` | access | `api` | Status uang task. Satu tagihan untuk seluruh pekerja. |
| `POST` | `/tasks/{task}/payment/hold` | access | `api` | Transfer diterima -> dana ditahan -> **daftar** activity dibuka. |

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
bash docs/smoke.sh                   # 66 pemeriksaan
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
