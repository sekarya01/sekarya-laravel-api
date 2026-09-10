# Sekarya API

Laravel 11.55.1 (dipin persis) / PHP 8.3-8.4 REST API. **Action-Based Architecture**
(Clean Architecture / DDD Lite).

> Diturunkan dari Laravel 13 agar cocok dengan katalog installer hosting. Konsekuensinya
> tercatat di README, bagian **Konsekuensi memakai Laravel 11** — baca sebelum menyentuh
> `app/Models/User.php` atau menaikkan versi PHP ke 8.5.

The full blueprint lives in the `laravel-action-api` skill — invoke it before writing code here.
This file records only what is specific to *this* project.

## Request lifecycle

```
FormRequest (shape validation) -> DTO (app/Data) -> Action (app/Actions)
  -> Model (app/Models) -> API Resource (app/Http/Resources) -> JSON
```

Business logic lives **only** in Action classes. Never in Controllers, Models, Requests or Resources.

## Project decisions

| Decision | Value | Note |
|---|---|---|
| Database | **MySQL 8+ / InnoDB** | Switched from SQLite on 2026-09-08 by explicit instruction. |
| Auth | Sanctum (`auth:sanctum`) | via `php artisan install:api`. |
| Tests | PHPUnit 11 (Laravel 11 belum mendukung 12) | Pest is *not* installed. |
| Money | integer, smallest unit | `transactions.deal_price` is `unsignedBigInteger`. |
| Pagination | cursor only | `paginate()` is banned. See below. |
| API prefix | `/api/v1`, routes named `v1.*` | one route line per invokable controller. |

## MySQL notes you must not forget

- **`lockForUpdate()` works here.** InnoDB translates it to `SELECT ... FOR UPDATE`,
  so the check-then-act in `AcceptBidAction`, `ConfirmPaymentAction` and
  `ReviewVerificationAction` is genuinely serialised. The DB-level unique indexes
  (`activities (task_id, worker_id)`, `admins.super_admin_lock`) are kept as the last
  line of defence anyway — a lock only holds inside a transaction, and writes from other
  paths may not take one.
- **Keyword search uses a FULLTEXT index**, never `LIKE`. `MATCH()` must name exactly
  the columns of the composite index `tasks_fulltext (title, description)` — a
  mismatched column list silently skips the index.
- **`innodb_ft_min_token_size` defaults to 3**, so two-letter words such as "AC" are
  not indexed and cannot be found. Lower it to 1 in the server config if short words
  must be searchable; that needs a server restart plus an index rebuild. InnoDB also
  applies a built-in (English) stopword list.
- **No CAST needed around bound floats.** MySQL coerces strings to numbers in numeric
  comparisons. This differs from SQLite, which compares across storage classes so
  a number is always less than text — if the connection is ever moved back to SQLite,
  every bound float in `TaskSearch::haversineSql()` and the radius comparison must be
  wrapped in `CAST(? AS REAL)` or the radius filter silently matches everything.
- **`engine` is pinned to InnoDB** in `config/database.php`. Foreign keys,
  transactions, row locks and FULLTEXT all require it; leaving it null defers the
  choice to the server default, which varies between environments.
- Distance filtering is a bounding box on the `(latitude, longitude)` index first,
  haversine second. Never haversine alone — a computed expression cannot be indexed,
  so it would scan the whole table.

## Deploy

Panduan shared hosting: `docs/DEPLOYMENT.md`. Dua artefak yang harus tetap seiring, dan
keduanya dijaga test:

- **`database/schema/sekarya-install.sql`** — pemasangan sekali jalan untuk hosting tanpa
  SSH. Dibuat oleh `php artisan sekarya:build-install-sql`, **jangan disunting tangan** dan
  jangan dibuat dengan `mysqldump`: mysqldump mengurutkan tabel secara alfabetis, sehingga
  `activities` dibuat sebelum `tasks`/`users`/`payments` yang dirujuknya. Dump itu hanya
  selamat karena `FOREIGN_KEY_CHECKS=0` di dalam komentar bersyarat `/*!40014 ... */` —
  dan phpMyAdmin melewati komentar itu, sehingga impornya berhenti di `CREATE TABLE`
  pertama tanpa keterangan. Perintahnya mengurutkan menurut ketergantungan.
  Migrasi baru **wajib** ikut ke sini; `tests/Feature/Deployment/InstallSchemaTest.php`
  menggagalkan suite kalau tidak, dan menolak data selain kategori, keahlian, dan migrasi —
  berkas ini dilacak git, dan riwayat git tidak bisa ditarik kembali.
- **`.env.production.example`** — template produksi, tiap nilai berkomentar.

Dua hal yang mudah terlewat:

- **Cache rute harus dibuat DI SERVER.** Rute `/docs` didaftarkan hanya saat `APP_ENV`
  bukan production; cache yang dibuat di laptop akan membawa spesifikasi API lengkap ke
  produksi.
- **`php artisan sekarya:admin create` adalah langkah pemasangan, bukan opsional.** Tanpa
  akun pengelola tidak ada yang bisa menyetujui verifikasi identitas atau mengonfirmasi
  transfer, dan alur pembayaran berhenti di antrean — padahal API-nya terlihat sehat.
  Akun itu sengaja tidak ada di berkas pemasangan SQL maupun seeder, karena keduanya
  dilacak git.

## Feed & pencarian nama

Filter feed: `q` (nama), `lat`/`lng`/`radius_km` (jarak), `posted_within_hours` (waktu),
`category_id`, `skills`/`match_my_skills`. **Tidak satu pun memakai `LIKE`** — nol
kemunculan `LIKE` di `app/`, dan itu harus tetap begitu.

Pencarian nama lewat tabel **`task_search`**, bukan indeks di `tasks` langsung:

- **Teks yang diindeks sudah dinormalisasi**, bukan judul mentah. `App\Support\SearchTerms`
  adalah SSOT-nya dan dipakai di DUA sisi — saat mengindeks dan saat mencari. Kalau kedua
  sisi memakai aturan berbeda, pencarian gagal **tanpa galat**: hasilnya cuma kosong.
- **Tabel terpisah, bukan kolom di `tasks`.** Feed memakai `select('tasks.*')`; kolom teks
  besar di `tasks` akan ikut terbaca setiap permintaan padahal tak pernah ditampilkan.
- **Barisnya dijaga hook `saved` di model `Task`**, bukan di Action. Ada tiga jalur yang
  menulis judul (buat, sunting, factory), dan indeks yang meleset tidak menimbulkan galat —
  task itu sekadar tidak pernah muncul di pencarian.
- **Kata pendek** (`ac`) disimpan bersentinel (`zqac`) supaya melewati
  `innodb_ft_min_token_size` bawaan 3. Mesin dev ini menyetelnya ke 1, jadi test pencarian
  "ac" di sini **lulus palsu** — yang diuji adalah invariannya: sisi kueri tidak pernah
  mengeluarkan kata di bawah 3 huruf.
- **Imbuhan dipenggal HANYA saat mengindeks**, tidak saat mencari. Akar salah penggal di
  indeks cuma jadi kata yang tak pernah dicari; di sisi kueri ia langsung jadi hasil salah.
- Rencana kueri diperiksa pada **20.000 task**: `Full-text index search on task_search`
  lebih dulu, lalu lookup primary key. Dengan tabel kecil MySQL wajar memilih memindai
  `tasks` — jangan menilai rencana kueri dari tabel yang isinya beberapa baris.

## Satu task, banyak pekerja

`tasks.workers_needed` (default 1) adalah berapa orang yang **diterima**, bukan batas
pelamar. **Lelangnya tetap terbuka**: siapa pun boleh menawar, dan pemberi kerja memilih
pemenangnya berdasarkan harga penawaran. Mengisi slot terakhir menutup lelang dan menolak
pelamar yang masih menunggu.

> Jangan menambahkan kuota pelamar di `PlaceBidAction`. Itu pernah dicoba dan salah:
> membatasi pelamar ke jumlah slot menghapus perbandingan tawaran — pada task satu orang
> ia menghapus lelangnya sama sekali, dan pelamar pertama jadi satu-satunya kandidat.

Yang tidak boleh "dirapikan":

- **Tidak ada `tasks.worker_id` / `accepted_bid_id`.** Satu kolom tidak bisa menyimpan
  tiga puluh nilai, dan menyimpannya "untuk yang satu orang" berarti dua sumber kebenaran
  untuk pertanyaan yang sama. Sumbernya baris `bids` berstatus `accepted`.
- **`tasks.agreed_amount` adalah TOTAL seluruh pekerja**, bukan harga satu orang. Karena
  itu harga referensi kategori dihitung dari `bids.amount` yang diterima — memakai total
  akan menggeser median kategori sebesar jumlah pekerjanya.
- **Satu `payment` per task, banyak `activities`.** Pemberi kerja transfer sekali;
  pembagiannya di `activities.agreed_amount` yang disalin dari penawaran masing-masing.
  Penjaga "tidak ada activity tanpa dana ditahan" kini `unique (task_id, worker_id)`,
  bukan lagi `unique (payment_id)`.
- **Status task mengikuti AGREGAT, bukan pekerja tercepat.** `submitted` hanya kalau semua
  sudah menyerahkan; `completed` + dana dilepas hanya kalau semua disetujui. Melepas pada
  persetujuan pertama akan mengeluarkan seluruh tagihan untuk satu orang.
- **Dana tidak bisa ditahan sebelum perekrutan selesai.** Tagihan sudah ada sejak pelamar
  pertama diterima, jadi tanpa penjaga itu pekerja yang direkrut belakangan tidak akan
  pernah punya activity.
- **`held` HANYA bisa dicapai dari sisi pengelola.** Pemberi kerja memanggil
  `POST /tasks/{task}/payment/hold` (→ `awaiting_confirmation`); yang menahan dana dan
  membuka activity `POST /admin/payments/{payment}/confirm`. Dulu satu panggilan itu
  mengerjakan keduanya, artinya pemberi kerja menyatakan sendiri uangnya sudah masuk —
  dan pekerja yang menanggung kalau ternyata tidak. Jangan pernah menambahkan kembali
  transisi `pending → held`; `PaymentStatus::canTransitionTo()` yang menjaganya, dan ada
  test khusus untuk itu.
- **Path `payment/hold` sengaja tidak diganti nama** walaupun ia tidak lagi menahan dana,
  supaya klien yang sudah ada tidak perlu diubah. Karena itu controller-nya tetap
  `HoldPaymentController` (mengikuti nama rute, seperti seluruh controller lain di
  berkas rute), sedangkan Action-nya `ReportTransferAction` — nama kelas domain harus
  menyebut apa yang benar-benar dikerjakannya. Kalau path ini suatu saat ikut diganti,
  itu perubahan yang memutus klien dan harus diumumkan sebagai BREAKING CHANGE.
- **`reviews` unique-nya `(task_id, reviewer_id, reviewee_id)`.** Dengan kunci lama,
  pemberi kerja yang merekrut 30 orang hanya bisa menilai satu dari mereka.
- `POST /tasks/{task}/start` menurunkan target ke jumlah yang sudah diterima lalu menutup
  lelang — untuk pekerjaan bertanggal yang tidak mendapat pelamar sebanyak targetnya.

## Profil pekerja dipisah dari akun

`users` menyimpan ORANGNYA, `user_workers` menyimpan sisi PEKERJANYA — satu baris per
orang, lahir saat ia pertama kali menang lelang, disetujui pekerjaannya, dinilai, atau
mengisi `PUT /me/worker`. Yang dipisah bukan populasinya (tidak seperti `admins`): ini
orang yang sama dalam peran yang berbeda.

| | `users` | `user_workers` |
|---|---|---|
| Identitas | nama, email, HP, **gender**, **birth_date** | — |
| Tampilan pekerja | — | `display_name`, `contact_phone`, `avatar_path` (semua NULLABLE) |
| Alamat | domisili orangnya | alamat kerja (NULLABLE, sebagai satu kesatuan) |
| Lokasi kerja | — | `latitude`/`longitude`/`radius_km` |
| Reputasi pekerja | — | `worker_rating_avg`, `_count`, `tasks_completed`, `bids_won` |
| Reputasi pemberi kerja | `poster_rating_*`, `tasks_posted` | — |

Yang tidak boleh "dirapikan":

- **Kolom identitas di `user_workers` adalah PELENGKAP, bukan salinan.** NULL berarti
  "pakai punya akun", bukan "kosong", dan resolusinya HANYA di `App\Models\UserWorker`
  (`resolvedName()`, `resolvedPhone()`, `resolvedAvatarPath()`, `resolvedAddress()`).
  Kalau kolom-kolom itu wajib diisi, dua tabel menyimpan jawaban atas pertanyaan yang
  sama dan keduanya bisa benar sendiri-sendiri: ganti nama di profil akun berhenti
  terlihat di profil pekerja, tanpa galat apa pun.
- **`gender` dan `birth_date` TIDAK ada di `user_workers`**, dan `UpsertWorkerProfileRequest`
  tidak menerimanya. Orang tidak berganti tanggal lahir saat berpindah mode. API tetap
  mengeluarkan `gender` dan `age` di profil pekerja — lewat relasi, bukan lewat kolom.
- **Alamat diresolusi sebagai SATU KESATUAN** (`hasOwnAddress()`). Kalau tiap kolom jatuh
  sendiri-sendiri ke akun, pekerja yang menulis alamat kerjanya di kota lain mendapat
  gabungan dua alamat — jalannya dari profil pekerja, kotanya dari domisili akun. Itu
  alamat yang tidak pernah ada, dan pemberi kerja akan mendatanginya.
- **`ready_to_work` menuntut DUA hal: baris profil DAN verifikasi `identity`
  berstatus `verified`.** Profil saja tidak cukup — siapa pun bisa membuatnya sendiri
  lewat satu panggilan; yang membuatnya berarti adalah persetujuan pengelola, dan itu
  tidak bisa diberikan sendiri. Rekening bank sengaja TIDAK ikut: ia syarat untuk
  dibayar, bukan untuk boleh bekerja. `GET /workers` memakai gerbang yang sama persis —
  daftar yang memuat orang tanpa verifikasi akan membantah penandanya sendiri di baris
  yang sama.
- **`PUT /me/worker` menolak akun tanpa `gender` dan `birth_date`** (`profile_incomplete`,
  422, `context.missing` menyebut field mana). Endpoint itu TIDAK menerima kedua field
  tersebut walau satu panggilan akan lebih enak: identitas hanya boleh punya satu jalur
  tulis. Baris profil yang terlanjur ada tanpa identitas dibiarkan — reputasi menempel
  padanya — dan yang menjaga daftar tetap bersih adalah `ready_to_work`, bukan
  penghapusan baris.
- **UMUR DIHITUNG, TIDAK DISIMPAN.** `User::age()` menurunkannya dari `birth_date` setiap
  kali dibaca. Kolom `age` akan salah pada hari ulang tahun setiap penggunanya dan tidak
  ada kejadian di aplikasi ini yang bisa memicu pembaruannya — tidak ada permintaan HTTP
  yang datang karena seseorang bertambah tua. Kolom turunan MySQL juga tidak bisa:
  `CURDATE()` non-deterministik, dan GENERATED menolaknya. Batas umurnya (17-100) di
  `config/sekarya.php` → `profile`, bukan sebagai literal di aturan validasi.
- **Agregat reputasi tidak mass-assignable, dua lapis.** Tidak ada di aturan validasi DAN
  tidak ada di `$fillable`. Yang menulisnya hanya `AcceptBidAction`,
  `ApproveActivityAction`, dan `CreateReviewAction` — yang terakhir menghitung ulang dari
  tabel `reviews`, bukan menambah inkremental.
- **`User::$with = ['workerProfile']`.** Blunt, dan disengaja: hampir setiap tempat yang
  menampilkan pengguna butuh reputasinya, dan satu Action yang lupa eager-load
  menghasilkan N+1 yang tidak menimbulkan galat apa pun.
- **`ListBidsAction` sort=rating memakai LEFT join, bukan inner.** Baris `user_workers`
  baru lahir saat orangnya pertama kali menang; inner join akan MENGHILANGKAN penawaran
  dari pekerja baru — pada urutan yang dipakai pemberi kerja untuk memilih orang.
- **Jalur BACA tidak boleh membuat baris.** `workerProfileOrNew()` untuk GET,
  `workerProfileOrCreate()` untuk Action. Kalau `GET /me/worker` membuat baris,
  `configured` tidak akan pernah bisa menjawab pertanyaan yang ia ada untuk menjawabnya.
- **`down()` migrasi perpindahan tidak boleh memakai `after()`.** Kolom-kolom itu dulu
  duduk sesudah `users.skills`, dan kolom itu sudah dihapus ketika keahlian menjadi
  relasi — `after('skills')` membuat SELURUH rollback gagal dengan "Unknown column".
  Sudah pernah terjadi; `tests/Feature/Deployment/WorkerAggregateMigrationTest.php`
  menjalankan siklus maju-mundur-maju dengan data sungguhan di basis data sekali-pakai.

Tabelnya bernama **`user_worker_verifications`** (dulu `user_verifications`) sejak
`ready_to_work` bergantung padanya: ia gerbang pekerja, bukan catatan di samping akun.
**FK-nya tetap `user_id` ke `users`, bukan ke `user_workers`** — pemberi kerja juga
mengajukan verifikasi identitas, dan badge itu dibaca pekerja saat menimbang siapa yang
mempekerjakannya. Harganya: nama tabel menyebut "worker" padahal sebagian isinya milik
pemberi kerja. Nama KELAS modelnya masih `UserVerification` dengan `$table` eksplisit —
utang yang disengaja, lihat komentar di modelnya.

Batas pengungkapan yang menyertainya: **orang lain melihat `age`, tidak pernah
`birth_date`** (tanggal lahir persis dipakai bank dan layanan publik sebagai verifikasi),
dan lokasi kerja keluar sebagai `as_worker.work_area` sebatas kota + radius — tanpa jalan
dan tanpa koordinat. Pengelola melihat tanggalnya, karena verifikasi identitas
mencocokkannya dengan KTP.

## Auth & security

**Dua populasi pemilik token, bukan satu tabel dengan kolom peran.** Pengguna di
`users` dengan guard `sanctum`; pengelola di `admins` dengan guard `admin`. Rinciannya
di bagian **Pengelola** di bawah — termasuk mengapa `config/auth.php` HARUS menyebut
`provider` setiap guard.

Three layers, all declared in `routes/api.php` so the whole rule set reads in one file:

1. `auth:sanctum` — the token is valid
2. `abilities:token:access` — it is an **access** token, not a long-lived one.
   Without this layer a long-lived token could call the entire API, and it lives
   for 30 days. The Sanctum ability aliases are registered in `bootstrap/app.php`;
   they are **not** auto-registered in Laravel 11+, and a missing alias fails as an
   unknown middleware rather than loudly.
3. `throttle:<limiter>` — every route is rate limited. Limiters live in
   `RateLimitServiceProvider`, numbers in `config/sekarya.php`.

**Registration never activates an account.** `POST /auth/register` returns 202 with no
tokens; the account sits at `pending_verification` until a 6-digit code from email is
accepted by `POST /auth/verify-email`, which is the only path to `active` + a token pair.

**Token pair.** `access` lives 8 hours and is the only token that can call app
endpoints. `long_lived` lives 30 days and can *only* call `POST /auth/refresh`.
Refreshing revokes the previous access token, so the old one dies the moment a new one
is issued. Logout revokes everything, including the long-lived token — revoking only
the access token would leave a credential that can mint new access.

Things that must not be "tidied up":

- **`users.status` default is `pending_verification`, and `status` is deliberately NOT
  mass-assignable.** A privilege-bearing column must never be settable from an attribute
  array, and its default must be the least-privileged state. This combination already
  caught a real bug: `User::create(['status' => …])` silently dropped the value and the
  old `active` default let registration bypass verification entirely.
- **Failed verification attempts are recorded OUTSIDE the transaction.** Incrementing
  inside a transaction that then throws is rolled back, which silently disables the
  per-code attempt limit and leaves a 6-digit code brute-forceable.
- **`DB_TIMEZONE=+00:00`.** MySQL's session timezone otherwise follows the host
  (Asia/Jakarta here, +7), so SQL `now()` and PHP `now()` disagree by seven hours and
  every server-evaluated timestamp (`CURRENT_TIMESTAMP` defaults, `NOW()` in WHERE)
  uses a different clock from the code that wrote the data.
- **CORS allowed origins come from `CORS_ALLOWED_ORIGINS`**, never `*`, and
  `supports_credentials` stays false — this API uses Bearer tokens, not cookies.
- Login, resend-code and verify-email return **identical responses whether or not the
  email exists**. Otherwise they become account-enumeration tools.

## Pengelola (super_admin & admin)

Tabel `admins` sendiri, guard sendiri, ability token sendiri. **Bukan** `users` dengan
kolom peran: `users` punya jalur tulis publik (pendaftaran, sunting profil, pemulihan
sandi lewat alamat email), jadi kewenangan pengelola di tabel itu berarti setiap
kebocoran mass-assignment di alur pengguna berpotensi jadi kenaikan hak akses. Tabel ini
tidak punya satu pun jalur tulis publik.

> **`config/auth.php` HARUS menyebut `provider` untuk guard `sanctum` DAN `admin`.**
> Kalau guard `sanctum` tidak ada di berkas itu, Sanctum mendaftarkannya sendiri saat
> runtime dengan `provider => null` (`SanctumServiceProvider::register`), dan
> `Guard::hasValidProvider()` mengembalikan `true` tanpa memeriksa apa pun. Artinya guard
> itu menerima pemilik token **jenis apa pun**: token pengelola sah di seluruh endpoint
> pengguna, dan sebaliknya — tanpa galat, tanpa jejak, tanpa satu baris kode pun yang
> salah. Selama hanya ada satu model bertoken, ini tidak terasa. Test
> `AdminAuthApiTest` mencoba kedua arahnya.

Empat lapis di `/admin`, satu lebih banyak daripada endpoint pengguna:

1. `auth:admin` — token sah DAN milik `App\Models\Admin`
2. `abilities:admin:access` — jenis access, bukan long_lived. Lapis kedua di belakang
   guard: token pengguna tidak pernah membawa `admin:access`, jadi ia tetap ditolak
   kalau lapis pertama suatu hari hilang.
3. `admin.active` — status akun diperiksa **per permintaan**. Status tidak tersimpan di
   dalam token dan token itu hidup delapan jam; tanpa lapis ini, pencabutan kewenangan
   baru berlaku delapan jam kemudian.
4. `throttle:admin`

Dua peran. `AdminRole::canManageAdmins()` adalah SSOT-nya — dibaca middleware
`admin.manages-admins` DAN `AdminRole::can()`, jadi penambahan peran ketiga tidak bisa
memperbarui satu tempat saja.

| Peran | Jumlah | Bisa dihapus | Boleh |
|---|---|---|---|
| `super_admin` | **tepat satu** | tidak | semuanya + kelola akun pengelola |
| `admin` | berapa pun | ya | verifikasi, konfirmasi transfer, moderasi pengguna |

Yang tidak boleh "dirapikan":

- **`admins.role` default `admin`, `admins.status` default `suspended`, keduanya TIDAK
  mass-assignable.** Dua aturan yang harus berlaku bersamaan, dan ini bug yang sudah
  pernah terjadi sungguhan di `users`: nilai yang jatuh dari mass assignment hilang
  **tanpa galat**, sehingga default kolom yang menentukan hasilnya. Dengan default
  paling sedikit hak, kelalaian berakhir sebagai akun terkunci tanpa kewenangan — bukan
  super_admin yang lahir sendiri.
- **Satu super_admin dijamin BASIS DATA.** Kolom turunan `super_admin_lock` berisi `'s'`
  hanya untuk baris super_admin dan NULL untuk sisanya; indeks unique atasnya menolak
  baris kedua dengan `#1062`. Turunan (GENERATED), bukan kolom biasa yang diisi
  aplikasi — kolom biasa bisa melenceng dari `role` lewat satu UPDATE di phpMyAdmin.
- **super_admin tidak bisa dihapus, dan penjaganya hook `deleting` di model**, bukan
  hanya Action yang melayani endpoint DELETE. Penghapusan bisa datang dari command,
  tinker, atau Action lain yang belum ada. Ia juga tidak bisa dinonaktifkan: ia
  satu-satunya yang bisa membuat pengelola baru.
- **Tidak ada endpoint pendaftaran pengelola dan tidak ada seeder-nya.**
  `database/seeders` dan `database/schema/sekarya-install.sql` sama-sama dilacak git,
  jadi kredensial di dalamnya bisa dibaca siapa pun yang membuka repositori — pada akun
  paling berhak di seluruh aplikasi. Akun pertama lahir dari
  `php artisan sekarya:admin create`, yang menolak sandi contoh saat `APP_ENV=production`.
- **Peran TIDAK boleh datang dari payload.** `CreateAdminData` tidak punya field `role`;
  `CreateAdminAction` memaksanya `admin`. Kalau bisa dikirim klien, `POST /admin/admins`
  adalah jalan membuat super_admin kedua, dan yang menahannya cuma aturan validasi.
- **Gerbang peran memakai MIDDLEWARE, bukan `->can()`.** Penolakan lewat Policy keluar
  sebagai `AccessDeniedHttpException` bawaan Laravel — `{"message": "This action is
  unauthorized."}` tanpa kode mesin — sementara `CreateAdminAction` menolak hal yang
  sama dengan `admin_access_denied`. Satu kegagalan logis dengan dua bentuk respons
  memaksa klien bercabang pada `message`. (`->can()` tetap untuk aturan per-objek.)
- **Moderasi pengguna MENCABUT TOKEN.** Kolom status saja tidak menghentikan siapa pun:
  access token hidup delapan jam dan tidak menyimpan status di dalamnya. Tanpa
  `TokenIssuer::revokeAll()`, akun yang di-ban tetap bisa menawar sampai tokennya
  kedaluwarsa — long_lived-nya 30 hari.
- **`reinstate` tidak selalu ke `active`.** Akun yang belum pernah memverifikasi email
  kembali ke `pending_verification`. Kalau tidak, moderasi jadi jalan melewati
  verifikasi email: suspend lalu pulihkan.
- **`GET /admin/verifications/{verification}` menulis.** Detail itulah satu-satunya
  tempat NIK dan nomor rekening keluar terbaca — **utuh, tidak dimasker**. Itu
  keputusan pemilik proyek yang diambil eksplisit setelah opsi masker dan opsi
  "tidak ditampilkan" ditawarkan; jangan mengubahnya tanpa menanyakan ulang, karena
  yang hilang adalah satu-satunya cara verifikasi identitas bisa dikerjakan. Yang
  mengimbanginya: setiap pembacaan mencatat `verification.viewed` di
  `admin_audit_logs`. Keputusan bisa ditinjau dari statusnya;
  pembacaan tidak meninggalkan bekas apa pun kalau tidak dicatat. Daftar antreannya
  memakai **kelas Resource yang berbeda**, bukan penanda boolean — endpoint daftar
  secara harfiah tidak punya kode untuk mengeluarkan NIK.
- **Jejak audit ditulis DI DALAM transaksi Action-nya.** Kebalikan dari penghitung
  percobaan kode verifikasi, yang harus di luar: percobaan itu benar-benar terjadi
  walaupun permintaannya gagal, sedangkan jejak "pengelola menyetujui X" yang tertinggal
  setelah X dibatalkan adalah jejak yang berbohong.
- **`admin_audit_logs` append-only** (tidak ada `updated_at`) dan `admin_id`-nya
  `restrictOnDelete` — jejak tidak boleh bisa dihapus dengan cara menghapus pelakunya.
  Karena itu pula penghapusan pengelola adalah soft delete, dan alamat emailnya tetap
  terpakai selamanya.

Yang **belum** ada, dan sudah tercatat di `docs/API.md` bagian 15: endpoint membaca
jejak audit, dan signed URL untuk melihat foto KTP/selfie (sampai itu ada, penilaian
identitas hanya bertumpu pada data teks).

## Observability (Axiom)

Full guide: `docs/OBSERVABILITY.md`. Reusable rules and the leak table live in the
skill: `references/observability.md`. Config SSOT: `config/axiom.php`. Off by
default (`AXIOM_ENABLED=false`), and hard-off in `phpunit.xml`.

`app/Logging/Axiom/Redactor.php` is the **only** way data leaves for Axiom, and it
is a security control, not a utility. Three layers, all three required: key
denylist, **value-pattern scrubbing** (tokens, JWT, PEM, e-mail, phone, 16-digit
IDs), and size caps. Layer 2 is what covers the gap layer 1 leaves — every time
a payload gains a field, the denylist is behind and the patterns are not.
Headers use an **allowlist**, because `Authorization` and `Cookie` are always there.

Things that must not be "tidied up":

- **The e-mail subject is never logged.** In this app it reads
  `Kode verifikasi Sekarya: 623862` — it *contains* the credential.
- **Query bindings are never logged**, only the SQL with `?`. The bindings are
  where the e-mail, the NIK and the password hash actually are.
- **Stack traces are rebuilt from `getTrace()`, never `getTraceAsString()`**,
  which includes scalar call arguments — `Hash::check('rahasia', …)` verbatim.
- **Uploaded filenames are dropped**, only size + MIME are kept.
  `KTP_Budi_Prasetyo.jpg` names the person in metadata.
- **`AxiomLogger` must stay a singleton** — one buffer per process, or the
  `request_id` that ties a request's events together differs per source.
- **Request start time lives on `AxiomLogger`, not the middleware.** Laravel
  builds a *new* middleware instance for `terminate()`, so instance state does
  not survive. When it did, every request measured as billions of ms, so every
  request counted as "slow", and since slow requests are never sampled, sampling
  silently stopped working.
- **`config('axiom.auth_routes')` is how auth outcomes get logged.** This app
  never calls `Auth::attempt()`, so Laravel's `Login`/`Failed` events never fire.
- **One `AXIOM_*` block in `.env`.** Duplicate keys make the *last* one win, so a
  leftover empty placeholder silently blanks a working token — the symptom is
  "kredensial belum diisi" while the token is plainly in the file.
  `php artisan sekarya:axiom` now reports duplicated keys by name.

Prove it rather than trusting it — run this after touching `config/axiom.php` or
after any endpoint gains a field:

```bash
php artisan sekarya:axiom --audit   # prints the filtered result, sends nothing
php artisan sekarya:axiom --ping    # one probe event to Axiom
```

## Layer rules (short form)

- Actions take a DTO and an explicit actor. No `request()`, `auth()`, `abort()`, `response()`.
- Actions throw `App\Exceptions\Domain\DomainException` subclasses. `bootstrap/app.php` is the
  single place that turns them into status codes.
- `UserRole::extrasKeys()` is the SSOT for which `extras` keys are valid per role.
  Validation, DTO sanitisation and the Transformer all read from it.
- Resources are the disclosure boundary: `npwp` and `gateway_payload` never leave the API.
  Tests assert this.
- Every `cursorPaginate()` query orders by `created_at` **and** `id` (use `scopeLatestFirst`).

## Commands

```bash
# Sekali saat setup: buat database-nya lebih dulu
#   CREATE DATABASE sekarya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
php artisan migrate:fresh --seed  # 26 tabel + kategori & skills
php artisan sekarya:admin create  # akun super_admin — SATU-SATUNYA cara membuatnya
php artisan serve                 # http://localhost:8000
php artisan test                  # 956 test, 3.501 asersi
composer test-report              # coverage/html + junit + testdox (lihat tests/README.md)
php artisan sekarya:axiom --audit # buktikan penyaringan PII sebelum kirim apa pun
php artisan test tests/Unit       # fast tier
./vendor/bin/pint                 # format (run before committing)
php artisan route:list --path=api
php artisan sekarya:demo --fresh     # seed fixtures + print dev tokens
bash docs/smoke.sh                # 160 live HTTP assertions, self-hosting server
```

## API contract

`docs/openapi.yaml` is the contract — OpenAPI **3.1**, hand-maintained. No swagger-php /
l5-swagger annotation package is installed, and none should be: the spec is the artefact,
not a by-product of docblocks.

```bash
npx --yes -p @redocly/cli redocly lint docs/openapi.yaml            # must stay clean
npx --yes -p @redocly/cli redocly build-docs docs/openapi.yaml \
  -o docs/api.html                                                  # regenerate the HTML
```

Served at `/docs` and `/docs/openapi.yaml` by `routes/web.php`, registered **only outside
production** — `docs/` lives outside `public/` so it is unreachable otherwise. A test boots
a production env in a subprocess and asserts the routes are gone. `docs/api.html` is
generated; gitignore it once the repo is initialised.

**Documentation drift is a test failure, not a habit.**
`tests/Feature/Docs/ApiDocumentationTest.php` asserts, in both directions, that every
`/api/v1` route has an operation in `docs/openapi.yaml`, that the spec promises no
endpoint that does not exist, that every domain error code is in the `DomainError` enum,
and that every endpoint appears in the `docs/API.md` summary table with the right count.
Add a route without documenting it and the suite names it.

`docs/API.md` is the human walkthrough. **When you change an API Resource, update
`docs/openapi.yaml` in the same commit.** Two rules the spec encodes and tests enforce:

- Validation failures return `{message, errors}`; business failures return
  `{message, code, context?}`. The two shapes stay disjoint so clients branch on `code`,
  never on `message`.
- `extras` always serialises as an object (`{}` when empty, never `[]`). A key that flips
  between array and object breaks generated clients.

## Scaffold a new feature slice

```bash
bash ~/.claude/skills/laravel-action-api/scripts/scaffold_feature.sh <Domain> <Verb>
```

Generates DTO + Request + Action + Controller + Resource + test stub, then finish the TODOs
and register the route.

## Reference implementation

`Activity` create/list is the worked example of every rule above — read it before adding a
new slice:

- `app/Actions/Activity/CreateActivityAction.php` — the Transaction activation gate
- `app/Actions/Activity/ListActivitiesAction.php` — cursor pagination
- `app/Http/Resources/Api/V1/UserProfileResource.php` — role-conditional `extras` mapping
- `tests/Unit/Actions/Activity/` — how Action tests are written (DTO built directly)
