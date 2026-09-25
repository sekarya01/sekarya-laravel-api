# Perubahan Backend untuk UI Redesign Sekarya

> Audit 2026-09-25. Sumber UI: `sekarya design layout/sekarya-ui/screens/*.html` (42 layar) + `sekarya_design_layout.md` (App Flow, UX Enhancements).
> Sumber API: `routes/api.php`, `app/Http/Resources/Api/V1/*`, `app/Http/Requests/Api/V1/*`, `app/Actions/*`, `database/migrations/*`, `docs/API.md`.
> Referensi baris (`file:line`) merujuk ke HEAD `35e9351` + perubahan working tree yang belum di-commit (filter `me/wallet/entries`: `ListWalletEntriesRequest`, `ListWalletEntriesAction`). **Filter riwayat saldo baru jalan di produksi setelah perubahan itu di-commit dan di-deploy.**
> Dokumen ini hanya analisis, tidak ada kode yang diubah.

---

## 1. Ringkasan

| Status | Jumlah | Keterangan |
|---|---:|---|
| Sudah ada | **24** | Bisa dipakai langsung, paling banyak hanya perlu pemetaan di mobile |
| Perlu ubah | **17** | Endpoint sudah ada, perlu field/parameter/aturan tambahan |
| Perlu baru | **17** | Endpoint/tabel baru |
| UI-only / keputusan produk | **20** | Konten demo/klaim marketing: tidak perlu BE, atau perlu diputuskan dulu |

**P0 (wajib supaya UI inti tidak berbohong atau buntu): 4 item**

1. **U2**: alamat detail dan koordinat presisi tugas ikut terkirim ke semua orang sebelum deal, padahal UI menjanjikan "hanya dibagikan setelah Mitra disetujui".
2. **U1**: layar Masuk meminta "Email atau Nomor HP", tapi `auth/login` hanya menerima email/username.
3. **B1**: layar Isi Saldo menampilkan rekening tujuan (bank, nomor, nama penerima), tapi API tidak punya sumbernya.
4. **B2**: "Total Masuk/Keluar" (Riwayat) dan "Pendapatan minggu ini" (Beranda Mitra) butuh agregat dari server. Menjumlah halaman cursor di klien pasti salah, dan angka pendapatan di mobile sekarang memang sudah salah (lihat `api-wallet-reviews.md`).

---

## 2. Tabel kebutuhan

Legenda prioritas: **P0** wajib untuk UI inti · **P1** penting, UI bisa tayang dengan degradasi · **P2** opsional/lanjutan.

### 2a. Sudah ada (24)

| # | Fitur | Screen | Endpoint / field saat ini | Catatan mobile |
|---|---|---|---|---|
| S1 | Daftar 2 langkah (nama depan/belakang, email, HP opsional, kota domisili, sandi) | daftar-langkah-1/2 | `POST auth/register` (`routes/api.php:123`), `RegisterRequest.php:28-35` | — |
| S2 | Verifikasi email & lupa kata sandi (tautan email) | lupa-kata-sandi-* | `POST auth/verify-email` `:129`, `POST auth/forgot-password` `:135` | Catatan "Belum ada reset kata sandi" di `docs/API.md:1941` sudah basi |
| S3 | Kategori + "Acuan harga" / "Rekomendasi Sekarya Rp120–180rb" | pasang-tugas-3, ubah-tugas, info-tugas | `GET categories` `:163`, `CategoryResource.php:22-31` (`reference_price.min/max/median/from_real_data`), snapshot `TaskResource.php:28` `budget.reference_median` | Wajib bedakan `from_real_data=false` ("perkiraan") |
| S4 | Pasang tugas: judul, deskripsi, foto ≤5, kategori, lokasi+kota, patokan, chip kondisi (parkir/gang/tingkat), jadwal mulai/selesai, jumlah pekerja, budget | pasang-tugas-1..4 | `POST tasks` `:245`, `CreateTaskRequest.php:15-51` (`options[]{label,value}`, `photos[]`, `needed_at`, `end_at`, `workers_needed` 1–500); `POST uploads` `:238` | Chip kondisi dan "Alat: Tangga tersedia" dikirim sebagai `options` |
| S5 | Dana ditahan dari saldo saat pasang, "Saldo mencukupi/kurang" | pasang-tugas-4, dialog-sukses-tugas-sudah-tayang | `TaskEscrow.php:107-121` (debit `task_hold`), `422 insufficient_balance` + `context.shortfall`; saldo di `UserResource.php:73-75` | — |
| S6 | Ubah tugas (parsial, hanya `draft/open`) | ubah-tugas | `PUT tasks/{task}` `:252`, `UpdateTaskRequest.php:33-63`, `task_not_editable` | — |
| S7 | Feed kerja: cari, rentang budget, radius + jarak, jumlah penawar, pemberi kerja terverifikasi + rating, "sudah dilamar", "10 mnt lalu" | beranda-cari-kerja, filter-tugas-bottom-sheet | `GET tasks` `:242`, `ListTasksRequest.php:22-54` (`q`, `budget_from/to`, `lat/lng/radius_km`, `posted_within_hours`), `TaskResource.php:37,60-67` (`bids_count`, `distance_km`, `poster`, `my_bid`) | Multi-kategori dan jadwal: lihat U9/U14 |
| S8 | Ajukan penawaran (tarif tetap = budget), kuota penuh | detail-peluang, dialog-sukses-penawaran-terkirim | `POST tasks/{task}/bids` `:280` (`amount` = `budget_min`), `hiring.slots_remaining` `TaskResource.php:41-45` | — |
| S9 | Daftar penawar: rating, jumlah ulasan, kerja selesai, verifikasi, keahlian | detail-tugas, profil-publik-mitra | `GET tasks/{task}/bids?sort=rating` `:282`, `ListBidsAction.php:27-66`, `PublicUserResource.php:51-66` | Jarak penawar: U8 |
| S10 | Terima penawar (satu per satu), "Mulai dengan mitra terpilih/yang ada" | detail-tugas, dialog-konfirmasi-terima-penawaran | `POST bids/{bid}/accept` `:288`, `POST tasks/{task}/start` `:276` | Multi-pilih = accept berurutan lalu `start` |
| S11 | Alur kerja per pekerja: Menuju lokasi → konfirmasi tiba (pemberi kerja) → Mulai → Tandai selesai (+foto bukti, catatan) → Konfirmasi selesai; jam tiap langkah ("09.52 WIB") | detail-kerjaan-*, detail-tugas-sedang-dikerjakan | `activities/{a}/depart·arrived·start·submit·approve` `:309-318`; `ActivityResource.php:24-32` (`departed_at`, `arrived_at`, `started_at`, `submitted_at`, `proof_photos`, `worker_note`) | Teks bebas per langkah: B9. Wajib foto: U11 |
| S12 | Info tugas: nomor tugas, batas penawaran + sisa waktu, kuota, target selesai, alur perjalanan (diterbitkan, deal, berangkat) | info-tugas | `TaskResource.php:18,41-56` (`task_number`, `bidding_closes_at`, `hiring`, `end_at`, `created_at`, `dealt_at`), `activities[].departed_at` | "Diterbitkan" = `created_at` (tidak ada `published_at`) |
| S13 | Batalkan langsung + dana kembali ke saldo | dialog-batalkan-tugas-ini | `POST tasks/{task}/cancel` `:257`, nominal dari `payment.amount` (`PaymentResource.php:23`) | — |
| S14 | Minta batal (semua pekerja harus setuju), setuju/tolak, tarik | dialog-minta-batalkan-tugas, dialog-pemberi-kerja-minta-batal-mitra | `tasks/{task}/cancel-requests[/…/approve·reject·withdraw]` `:263-273`; `TaskCancelRequestResource.php:27,42` (`approvals_required`, `my_response`) | Push belum ada: U12 |
| S15 | Beri rating (bintang + komentar) | dialog-beri-rating | `POST tasks/{task}/reviews` `:323`, `CreateReviewRequest.php:15-24` | Tag: U6 |
| S16 | Ulasan per peran | profil-publik-mitra, semua-ulasan | `GET users/{user}/reviews?role=` `:325`, `ListUserReviewsRequest.php:21` | Filter bintang/cari: U7 |
| S17 | Profil publik: rating, ulasan, kerja selesai, kota, area kerja + radius, bergabung, tentang, keahlian, verifikasi KTP | profil-publik-mitra | `PublicUserResource.php:38-72`, ditempel di `poster`/`workers`/`bid.bidder` | Endpoint baca tunggal: B4 |
| S18 | Saldo: kartu saldo, permintaan menggantung, seksi Pengeluaran/Isi saldo, riwayat dengan filter tanggal/nominal/jenis/cari | saldo, riwayat-saldo, filter-riwayat-saldo | `me/wallet*` `:215-228`; `ListWalletEntriesRequest.php:21-41` (`types[]`, `direction`, `q`, `from/to`, `min_amount/max_amount`); topups/withdrawals `?status=` | Filter masih di working tree, belum deploy |
| S19 | Batalkan isi saldo yang menggantung | saldo | `POST me/wallet/topups/{topup}/cancel` `:221` | — |
| S20 | Tarik saldo ke rekening terverifikasi (otomatis), "Tarik Semua" | tarik-saldo | `POST me/wallet/withdrawals` `:225` (tujuan diambil dari verifikasi); bank + nama pemilik di `GET me/verifications` (`VerificationResource.php:39-46`); "Tarik Semua" = `wallet.balance` | Nomor tersamar: U4 |
| S21 | Daftar jadi mitra pakai kode + tampil hanya bila ada kode aktif | dialog-daftar-jadi-mitra, akun | `POST me/worker/redeem` `:181`, `GET me/worker/invite-availability` `:187` | — |
| S22 | Edit profil: nama, username, kota, bio, foto | edit-profil | `PATCH me` `:168`, `UpdateProfileRequest.php:18-49`, `POST uploads purpose=avatar` | Nomor HP: U5 |
| S23 | Mode gelap | akun | `PATCH me {theme: light\|dark\|system}` `UpdateProfileRequest.php:51`, `UserResource.php:48` | Boleh tetap lokal; kalau ingin ikut akun, pakai field ini |
| S24 | Beranda: "Sedang berjalan" (pekerja, rating, tahap, estimasi selesai), stat mitra (kerja selesai, reputasi) | beranda-cari-bantuan, beranda-cari-kerja, tugas | `GET tasks/posted` / `tasks/worked` (`ListTasksAction.php:61-95`, memuat `activities.worker`), `end_at`; `UserResource.php:55-60` `as_worker` | ETA "Tiba 15 mnt lagi": B8 |

### 2b. Perlu ubah (17)

| # | Fitur | Screen | Status | Endpoint/field saat ini | Perubahan BE | Prio |
|---|---|---|---|---|---|---|
| U1 | Masuk dengan nomor HP | masuk, lupa-kata-sandi-dialog | Perlu ubah | `POST auth/login`, `LoginRequest.php:16-17` hanya `email`/`username` | Terima `phone` (normalisasi `08…`→`+628…`), `required_without_all`. *Alternatif tanpa BE: ubah copy jadi "Email atau username".* | **P0** |
| U2 | Privasi lokasi sebelum deal ("Detail alamat hanya dibagikan setelah Mitra disetujui", "Mitra hanya menerima nomor unit & rute setelah penawaran disepakati") | ubah-tugas, pilih-lokasi, detail-peluang | Perlu ubah | `TaskResource.php:30-36` mengirim `location.text` + lat/lng presisi ke siapa pun yang boleh `view` (`TaskPolicy.php:20`: semua orang selama task menerima penawaran) | Untuk viewer yang bukan poster atau pekerja yang sudah deal: `location.text=null`, koordinat dibulatkan (3 desimal ≈110 m), tambah `location.is_precise`, dan opsional `location.area` (kecamatan/kelurahan untuk kartu "Dago, Bandung"; kolom baru `tasks.area` varchar(80)). `distance_km` tetap dihitung dari koordinat asli. | **P0** |
| U3 | Baris saldo menampilkan judul tugas ("Pindahan Lemari Lantai 2 · Escrow Ditahan", "Bayar Tugas #SKR-8812 · Cuci AC") dan bisa dibuka | saldo, riwayat-saldo | Perlu ubah | `WalletEntryResource.php:26-37`: hanya `reference_type` + `description` teks bebas (`'Dana tugas #TK-… ditahan'`, `TaskEscrow.php:112`) | Tambah `task: {id, task_number, title, category:{slug,name}}\|null` (resolve lewat `task_fund_movements`/`payments`/`activities`), eager-load di `ListWalletEntriesAction` | P1 |
| U4 | Rekening tujuan "BCA · Ratna Dewi · **** 4910 · Terverifikasi" | tarik-saldo | Perlu ubah | `VerificationResource.php:39-46` (`bank_code`, `account_holder_name`), `WalletWithdrawalResource.php:31-34` | Tambah `account_number_masked` ("•••• 4910", 4 digit terakhir) di kedua resource. Nomor utuh tetap tidak keluar. | P1 |
| U5 | Ubah nomor HP di Edit profil | edit-profil | Perlu ubah | `UpdateProfileRequest.php:18-51` tidak punya `phone` | Tambah `phone` (regex sama dengan `RegisterRequest.php:35`, unique kecuali diri sendiri), reset `phone_verified_at`. Email: jadikan read-only di UI (ganti email butuh alur verifikasi baru, keputusan produk). | P1 |
| U6 | Tag rating ("Tepat Waktu", "Kerja Rapi", "Sangat Ramah") | dialog-beri-rating | Perlu ubah | `CreateReviewRequest.php:15-24` (rating, comment, worker_id); tabel `reviews` tanpa kolom tag | Migrasi `reviews.tags` JSON; enum `ReviewTag`; request `tags[]` (maks 5); `ReviewResource` + `tags` | P1 |
| U7 | Semua ulasan: chip bintang, cari ulasan, nama tugas per ulasan ("Cuci AC Daikin 1 PK") | semua-ulasan, profil-publik-mitra | Perlu ubah | `ListUserReviewsAction.php:22-28` hanya filter `role`; `ReviewResource.php:16-23` tanpa task | Param `rating` (1–5) atau `rating_max` untuk "1-2★", `q` (CLAUDE.md melarang `LIKE` di `app/`: pakai FULLTEXT di `reviews.comment` + `SearchTerms`, atau minta pengecualian eksplisit seperti wallet entries); resource + `task:{id,title,category}` | P1 |
| U8 | Jarak penawar/pekerja ("★4,9 (41) · 1,1 km") | detail-tugas, detail-tugas-sedang-dikerjakan | Perlu ubah | `BidResource.php:16-31` tanpa jarak; koordinat pekerja (`user_workers.latitude/longitude`) sengaja tidak dibuka | Tambah `distance_km` terhitung di server (haversine koordinat task ↔ lokasi kerja pekerja, 1 desimal, `null` bila salah satu kosong) di `BidResource`, dan sama untuk `TaskResource.workers[]`. Koordinat tetap tidak keluar. | P1 |
| U9 | Filter kategori pilih >1 | filter-tugas-bottom-sheet | Perlu ubah | `ListTasksRequest.php:22` `category_id` tunggal | Tambah `category_ids[]` (maks 20), `whereIn` pada indeks `(category_id,status,created_at)` | P1 |
| U10 | Tab Tugas: Berjalan / Dikerjakan / Selesai / Dibatalkan | tugas | Perlu ubah | `ListTasksAction.php:65,90` hanya `status` tunggal | Tambah `statuses[]` di `tasks/posted` & `tasks/worked` supaya tiap tab bisa dipaginasi dengan cursor tanpa halaman kosong | P1 |
| U11 | "Foto Bukti Hasil Pengerjaan · Wajib 1 foto" | detail-kerjaan-tandai-selesai | Perlu ubah | `SubmitActivityRequest.php:17-18` `proof_photos` opsional; `StoreUploadController.php:17` hanya folder `tasks`/`avatars` | `proof_photos` `required\|min:1` (angka di `config/sekarya.php`); `purpose=proof` → `uploads/proofs`, validasi path milik folder itu | P1 |
| U12 | Pekerja langsung ditanya saat pemberi kerja minta batal; pemberi kerja tahu hasilnya | dialog-pemberi-kerja-minta-batal-mitra, dialog-minta-batalkan-tugas | Perlu ubah | `PushType.php` hanya bid & activity; tidak ada event pembatalan | Tambah `cancel_requested` (ke pekerja responden), `cancel_request_resolved` (`approved`/`rejected`, ke poster), `task_cancelled` (ke pekerja) di `PushMessages` | P1 |
| U13 | Ketersediaan mitra "Siap menerima kerja · Kamu tampil di pencarian mitra & dapat notifikasi tugas terdekat" | beranda-cari-kerja | Perlu ubah | Hanya lokal (`SettingsDataStore.kt:28` `AVAILABLE`), tidak ada di `user_workers` | Kolom `user_workers.is_available` (default true); `PUT me/worker {is_available}`; `GET workers?available=1`; `as_worker.is_available` di `PublicUserResource` (badge "Tersedia" di daftar penawar) | P1 |
| U14 | Filter jadwal "Hari ini / Besok / Minggu ini" | filter-tugas-bottom-sheet | Perlu ubah | Tidak ada filter `needed_at`; App Flow menyaring di klien, tapi itu merusak halaman cursor | `needed_from`/`needed_to` (ISO-8601 beroffset) + indeks `(status, needed_at)` | P2 |
| U15 | "Layanan Selesai 14" untuk pemberi kerja | akun | Perlu ubah | `as_poster` hanya `tasks_posted` (`UserResource.php:61-65`) | `as_poster.tasks_completed` (counter, dinaikkan saat task `completed`) | P2 |
| U16 | Profesi/judul mitra ("Teknisi AC", "Mitra Teknisi · Bandung") | profil-publik-mitra, dialog-konfirmasi-terima, dialog-terima-kasih-rating | Perlu ubah | Tidak ada; hanya `skills[]` | `user_workers.headline` varchar(60) via `PUT me/worker`, keluar di `as_worker.headline`. *Atau derivasi di klien dari skill pertama (tanpa BE).* | P2 |
| U17 | Rekomendasi harga "berdasarkan tugas serupa **di sekitarmu**" | pasang-tugas-3 | Perlu ubah | Acuan harga per kategori nasional (`RecomputeReferencePricesAction`) | Acuan per (kategori, kota) bila sampel cukup, fallback nasional; `GET categories?city=` | P2 |

### 2c. Perlu baru (17)

| # | Fitur | Screen | Status | Endpoint/field saat ini | Perubahan BE | Prio |
|---|---|---|---|---|---|---|
| B1 | Rekening tujuan isi saldo (bank, nomor, nama penerima, salin) + batas nominal ("Min. Rp10.000", "minimal penarikan Rp50.000") | isi-saldo, tarik-saldo | Perlu baru | Tidak ada; batas hanya di `config/sekarya.php` `wallet.*` dan dicerminkan manual di mobile (`WalletLimits`) | `GET me/wallet/config`: rekening tujuan (dari env/config), `min/max_topup`, `min/max_withdrawal`, `max_pending_requests` | **P0** |
| B2 | Total Masuk / Total Keluar, pendapatan minggu ini (+% vs pekan lalu), jumlah transaksi per bulan | riwayat-saldo, beranda-cari-kerja | Perlu baru | Hanya daftar `me/wallet/entries` bercursor | `GET me/wallet/summary?from&to[&group=month]`: `SUM` per `direction`/`type` pada indeks `(wallet_id, created_at, id)` | **P0** |
| B3 | Notifikasi in-app (lonceng + badge) | header beranda-*, tugas, akun | Perlu baru | Push FCM saja (`PushMessages.php`, `me/devices`); tidak ada riwayat | Tabel `user_notifications`; ditulis di jalur yang sama dengan push; `GET me/notifications`, `GET me/notifications/unread-count`, `POST me/notifications/{id}/read`, `POST me/notifications/read-all` | P1 |
| B4 | Buka profil publik satu orang (dari notifikasi/deep link, bukan hanya titipan layar asal) | profil-publik-mitra | Perlu baru | Tidak ada (`users/{user}` hanya admin, `routes/api.php:456`); mobile memakai `ProfileStore` | `GET users/{user}` → `PublicUserResource` (+skills); 404 untuk akun non-aktif | P1 |
| B5 | Ringkasan ulasan: "★4.9 dari 41", "92% 5 Bintang", jumlah per chip ("5★ (30)", "4★ (8)") | semua-ulasan | Perlu baru | Tidak ada agregat distribusi | `GET users/{user}/reviews/summary?role=` | P1 |
| B6 | Alamat tersimpan di server (nama, titik peta, kota, detail ≤250, "Terverifikasi"); dasar jarak feed & "Pakai alamat tersimpan" | alamat-tersimpan, pasang-tugas-2, filter-tugas | Perlu baru | Tidak ada; lokal DataStore (`api-core.md`: "Belum ada endpoint alamat") | Tabel `user_addresses` (1:1, `user_id` unique); `GET/PUT/DELETE me/address`. Tidak pernah keluar di `PublicUserResource`. | P1 |
| B7 | Hitungan per tab ("Berjalan (2)", "Dikerjakan (1)") dan per kategori feed ("Pindahan (3)") | tugas, beranda-cari-kerja | Perlu baru | Cursor tanpa total (disengaja) | `GET tasks/posted/counts`, `GET tasks/worked/counts` (COUNT per grup status); counts per kategori feed opsional | P2 |
| B8 | ETA "Tiba 15 mnt lagi", "Sedang dalam perjalanan (2.4 km)", live location | beranda-cari-bantuan, dialog-minta-batalkan-tugas | Perlu baru | Hanya `departed_at` | `POST activities/{a}/location {lat,lng}` (throttle, hanya `on_the_way`), `activity.live: {distance_km, eta_minutes, updated_at}`; retensi pendek. *Alternatif murah: tampilkan "Berangkat 09.40" dari `departed_at`.* | P2 |
| B9 | Catatan update per pekerja ("09.52 WIB · Tiba di lokasi dan mulai angkut lemari") | detail-tugas-sedang-dikerjakan | Perlu baru | Jam per langkah sudah ada (S11), teks bebas tidak ada | `activity_updates` (activity_id, note ≤200, photo?, created_at); `POST activities/{a}/updates`; `latest_update` di `ActivityResource`. *Tanpa BE: tulis kalimat tetap per status + jam.* | P2 |
| B10 | Checklist "Persiapan Mitra 3/3" & checklist sebelum "Tandai selesai" | detail-kerjaan-menuju-lokasi, -tandai-selesai | Perlu baru | Tidak ada di model | `tasks.checklist` JSON (dibuat poster/template kategori) + `activities.checklist_state`; atau jadikan UI lokal saja | P2 |
| B11 | Bookmark/simpan tugas | beranda-cari-kerja (UX Enh. #10) | Perlu baru | Tidak ada | `task_bookmarks`; `PUT/DELETE tasks/{task}/bookmark`, `GET tasks/bookmarked`, `is_bookmarked` di feed | P2 |
| B12 | Pemilih kota (header Beranda, register, edit profil) | beranda-*, daftar-1, edit-profil, alamat-tersimpan | Perlu baru | Kota = teks bebas (`city` max 80) | `GET cities?q=` (master kab/kota + provinsi). *Atau daftar statis di app.* | P2 |
| B13 | Push tugas baru ke mitra terdekat + "Notifikasi telah dikirim ke ~14 mitra terdekat di area Coblong" | dialog-sukses-tugas-sudah-tayang, dialog-daftar-jadi-mitra ("Radius 5 km") | Perlu baru | Tidak ada `PushType` tugas baru | Job saat publish: cari `user_workers` `is_available` dalam radius; push `task_published`; respons `POST tasks` + `meta.notified_workers` | P2 |
| B14 | Kode unik 3 digit transfer manual | isi-saldo | Perlu baru | Tidak ada | `wallet_topups.unique_code` + `transfer_amount` (keputusan produk: mempermudah pencocokan mutasi) | P2 |
| B15 | Ulasan dengan foto ("Dengan Foto (12)") | semua-ulasan | Perlu baru | Tidak ada | `reviews.photos` JSON, `has_photos` filter | P2 |
| B16 | Konfirmasi selesai semua pekerja sekali tekan ("Konfirmasi Selesai & Rilis Dana") | detail-tugas-sedang-dikerjakan | Perlu baru | Per activity (`approve` `:317`) | `POST tasks/{task}/approve-all`. *Klien bisa loop per activity.* | P2 |
| B17 | Tombol telepon ke pemberi kerja/pekerja setelah deal | detail-kerjaan-* | Perlu baru | Nomor tidak pernah keluar di `PublicUserResource` (disengaja) | Keputusan produk. Bila ya: `contact_phone` hanya untuk pasangan yang sudah deal dan task aktif (`GET tasks/{task}/contacts`) | P2 |

---

## 3. Usulan kontrak (P0/P1)

Konvensi yang diikuti (dari `docs/API.md` & `CLAUDE.md`): prefix `/api/v1`, satu invokable controller per rute, amplop sukses `{data}`, galat bisnis `{message, code, context?}`, galat validasi `{message, errors}`, id publik ULID, uang integer rupiah, waktu ISO-8601 beroffset, daftar **cursor only** (`meta.next_cursor`, tanpa `total`), urut `created_at`+`id`. Setiap rute baru wajib masuk `docs/openapi.yaml` dan tabel ringkasan `docs/API.md` (`ApiDocumentationTest`).

### U1 · Login dengan nomor HP (P0)
```http
POST /api/v1/auth/login
{ "phone": "081234567890", "password": "••••••••" }
```
Aturan: tepat satu dari `email | username | phone`. Normalisasi ke `+62…` sebelum lookup (kolom `users.phone` sudah unique). Respons tetap `TokenPairResource`. Galat tetap `401 invalid_credentials` (jangan bedakan "nomor tidak terdaftar").

### U2 · Lokasi tersamar sebelum deal (P0)
`GET /tasks`, `GET /tasks/{task}` untuk viewer yang bukan poster dan bukan pekerja ber-bid `accepted`:
```json
"location": {
  "text": null,
  "area": "Coblong",
  "city": "Kota Bandung",
  "latitude": -6.892,
  "longitude": 107.617,
  "is_precise": false,
  "is_remote": false
}
```
Poster/pekerja yang sudah deal: `text` utuh, koordinat penuh, `is_precise: true`. Logikanya di resource (batas pengungkapan), dengan penentu akses di satu helper (`Task::revealsLocationTo(User)`). Tambah test disclosure.

### U3 · Referensi tugas di riwayat saldo (P1)
```json
{
  "id": "01M…", "type": "task_hold", "direction": "debit", "amount": 240000,
  "balance_after": 610000, "reference_type": "task_fund_movements",
  "description": "Dana tugas #TK-260924-AB12CD ditahan",
  "task": { "id": "01M20DM1…", "task_number": "TK-260924-AB12CD", "title": "Pindahan Lemari Lantai 2",
            "category": { "slug": "pindahan", "name": "Pindahan & Angkut" } },
  "created_at": "2026-09-24T10:15:00+07:00"
}
```
`task` = `null` untuk `topup`, `withdrawal`, `withdrawal_reversal`, `adjustment_*`.

### U4 · Rekening tersamar (P1)
`GET /me/verifications` (tipe `bank_account`) dan `WalletWithdrawalResource.destination`:
```json
{ "bank_code": "BCA", "account_holder_name": "Ratna Dewi", "account_number_masked": "•••• 4910" }
```

### U5 · PATCH me nomor HP (P1)
```http
PATCH /api/v1/me
{ "phone": "+6281234567890" }
```
`422 errors.phone` bila dipakai orang lain. `phone_verified` → `false` setelah berubah.

### U6 · Tag rating (P1)
```http
POST /api/v1/tasks/{task}/reviews
{ "rating": 5, "tags": ["on_time", "tidy", "friendly"], "comment": "Rapi dan tepat waktu", "worker_id": "01M…" }
```
Enum `ReviewTag`, dibedakan per `reviewer_role` (untuk poster→pekerja: `on_time`, `tidy`, `friendly`, `skilled`; untuk pekerja→poster: `clear_brief`, `friendly`, `on_time_payment`). Tag di luar peran → `422 errors.tags.*`. `ReviewResource` + `"tags": [...]` (selalu array).

### U7 · Filter ulasan + nama tugas (P1)
```http
GET /api/v1/users/{user}/reviews?role=poster&rating=5&q=rapi&per_page=20
GET /api/v1/users/{user}/reviews?role=poster&rating_max=2
```
Item + `"task": { "id": "01M…", "title": "Servis & Cuci AC Daikin 1 PK", "category": { "slug": "ac", "name": "AC & Elektronik" } }`.

### U8 · Jarak penawar/pekerja (P1)
`BidResource` dan `TaskResource.workers[]` (hanya untuk poster task itu):
```json
{ "id": "01M…", "amount": 120000, "status": "pending", "distance_km": 1.1, "bidder": { … } }
```

### U9 / U10 · Filter daftar tugas (P1)
```http
GET /api/v1/tasks?category_ids[]=3&category_ids[]=7&budget_from=50000&budget_to=500000&lat=-6.89&lng=107.61&radius_km=10
GET /api/v1/tasks/posted?statuses[]=submitted&statuses[]=disputed
```
`category_id` lama tetap diterima; bila keduanya dikirim, `422`.

### U11 · Bukti kerja wajib (P1)
```http
POST /api/v1/uploads            (multipart: file, purpose=proof)   → 201 {data:{path:"uploads/proofs/…jpg", url}}
POST /api/v1/activities/{a}/submit
{ "proof_photos": ["uploads/proofs/…jpg"], "worker_note": "Talang sudah lancar" }
```
Tanpa foto → `422 errors.proof_photos`. Path di luar `uploads/proofs/` → `422`.

### U12 · Push pembatalan (P1)
Payload `data` FCM mengikuti bentuk yang ada (`PushMessages::data`):
```json
{ "type": "cancel_requested", "task_id": "01M…", "cancel_request_id": "01M…" }
{ "type": "cancel_request_resolved", "task_id": "01M…", "result": "rejected" }
{ "type": "task_cancelled", "task_id": "01M…" }
```

### U13 · Ketersediaan mitra (P1)
```http
PUT /api/v1/me/worker
{ "is_available": false }
```
→ `WorkerProfileResource.is_available`; `PublicUserResource.as_worker.is_available`; `GET /workers?available=1`. Migrasi: `user_workers.is_available boolean default true` + indeks bila dipakai filter.

### B1 · Konfigurasi dompet (P0)
```http
GET /api/v1/me/wallet/config
```
```json
{
  "data": {
    "topup_accounts": [
      { "bank_code": "BCA", "bank_name": "Bank Central Asia", "account_number": "<dari env>", "account_holder": "<nama badan usaha>" }
    ],
    "limits": {
      "min_topup": 10000, "max_topup": 10000000,
      "min_withdrawal": 50000, "max_withdrawal": 10000000,
      "max_pending_requests": 3
    }
  }
}
```
Sumber: `config/sekarya.php` → `wallet.topup_accounts` (diisi dari env, bukan hard-code di repo). Nilai rekening asli wajib dicek manusia sebelum diisi.

### B2 · Ringkasan saldo (P0)
```http
GET /api/v1/me/wallet/summary?from=2026-09-01T00:00:00+07:00&to=2026-10-01T00:00:00+07:00
```
```json
{
  "data": {
    "from": "2026-09-01T00:00:00+07:00", "to": "2026-10-01T00:00:00+07:00",
    "credit_total": 550000, "debit_total": 230000, "entries_count": 4,
    "by_type": { "topup": 250000, "refund": 300000, "task_hold": 230000, "earning": 0 },
    "earning_total": 0
  }
}
```
`from` inklusif, `to` eksklusif (sama dengan filter entries), rentang maks 366 hari. Filter `types[]`/`direction` boleh diterima agar total mengikuti tab. "Pendapatan minggu ini" + "vs pekan lalu" = dua panggilan (minggu ini dan minggu lalu), atau `compare_previous=1` → `previous: {credit_total, earning_total}`.

### B3 · Notifikasi in-app (P1)
```http
GET  /api/v1/me/notifications?unread=1&per_page=20
GET  /api/v1/me/notifications/unread-count          → {"data":{"count":3}}
POST /api/v1/me/notifications/{notification}/read
POST /api/v1/me/notifications/read-all
```
```json
{
  "data": [
    { "id": "01M…", "type": "bid_placed", "title": "Servis AC Daikin", "body": "Budi mengajukan penawaran · 3 penawar",
      "task_id": "01M…", "activity_id": null, "read_at": null, "created_at": "2026-09-25T09:12:44+07:00" }
  ],
  "links": { … }, "meta": { "next_cursor": "…", "prev_cursor": null, "per_page": 20 }
}
```
Migrasi `user_notifications` (ulid, user_id, type, title, body, data JSON, read_at, created_at; indeks `(user_id, created_at, id)`, `(user_id, read_at)`). Ditulis oleh jalur yang sama dengan `SendPushNotification`, supaya push dan daftar tidak berbeda isi.

### B4 · Profil publik (P1)
```http
GET /api/v1/users/{user}      → {"data": PublicUserResource (+skills)}
```
Akun `suspended/banned/pending` → `404` (jangan konfirmasi keberadaan).

### B5 · Ringkasan ulasan (P1)
```http
GET /api/v1/users/{user}/reviews/summary?role=poster
```
```json
{ "data": { "rating_avg": 4.9, "rating_count": 41,
            "distribution": { "5": 30, "4": 8, "3": 2, "2": 1, "1": 0 } } }
```
Distribusi selalu 5 kunci (objek, bukan array). Persentase dihitung klien.

### B6 · Alamat tersimpan (P1)
```http
GET    /api/v1/me/address      → {"data": null} bila belum ada
PUT    /api/v1/me/address
DELETE /api/v1/me/address      → 204
```
```json
{ "label": "Rumah", "address_line": "Jl. … No. 42, RT 03/RW 07, Coblong (patokan …)",
  "city": "Kota Bandung", "province": "Jawa Barat",
  "latitude": -6.8915, "longitude": 107.6167 }
```
Validasi: `label` ≤40, `address_line` ≤250, koordinat wajib berpasangan (aturan yang sama dengan `me/worker`). Respons + `updated_at`. Mobile memindahkan `SettingsRepository.savedAddress` ke repo jaringan.

---

## 4. UI-only / keputusan produk

| # | Elemen di UI | Screen | Status BE | Keputusan yang dibutuhkan |
|---|---|---|---|---|
| X1 | Tier member "Silver" | akun | Tidak ada | Program loyalti ada atau tidak? Kalau tidak, hapus dari UI |
| X2 | "Poin Sekarya 450" | akun | Tidak ada | Sama dengan X1 |
| X3 | "Metode Pembayaran · QRIS & Virtual Account" | akun | Tidak ada (isi saldo = transfer manual, `docs/API.md` "Belum ada") | Hapus atau ganti "Transfer bank (manual)" sampai gateway ada |
| X4 | "Top-up Instant via QRIS", "via Mandiri Livin", "BCA Virtual Account" | saldo, isi-saldo | Channel tidak dicatat | Demo. Kalau channel perlu tampil, tambah `sender_bank` di topup |
| X5 | "Pusat Bantuan & CS 24 Jam", "Ajukan Kendala" | akun | Tidak ada | Link statis (WA/web) atau alur tiket/sengketa (belum ada jalan keluar dari `disputed`) |
| X6 | Garansi 14 hari / "Garansi Pengerjaan Ulang 7 Hari" / "Sekarya Amanah" | akun, profil, info-tugas | Tidak ada kebijakan di sistem | Klaim hukum. Putuskan kebijakannya dulu, baru copy |
| X7 | "Identitas terverifikasi (KTP & SKCK)" | profil-publik-mitra | Hanya `identity` & `bank_account` (`VerificationType`) | Hapus "SKCK", atau tambah tipe verifikasi baru |
| X8 | "Kata sandi ini juga untuk otorisasi transaksi saldo" | daftar-langkah-2 | Penarikan tidak meminta sandi | Klaim salah. Hapus copy, atau tambah konfirmasi sandi pada `POST me/wallet/withdrawals` |
| X9 | "Enkripsi standar perbankan", "Sekarya SafePay", "Rekber" | daftar-1, masuk, riwayat | — | Klaim marketing; pastikan akurat |
| X10 | "+18% dari pekan lalu" | beranda-cari-kerja | Bisa dari B2 (dua periode) | Tampilkan hanya bila B2 ada |
| X11 | "Tips Mitra: balas <15 menit = 2.5x peluang" | beranda-cari-kerja | Statistik fiktif | Konten editorial; jangan tampilkan angka tanpa data |
| X12 | "Paling diminati" (jam mulai populer) | pasang-tugas-3/4, detail-peluang, ubah-tugas | Tidak ada | Heuristik statis di app, atau endpoint statistik (P2) |
| X13 | "Rekomendasi 2 orang" (jumlah pekerja) | pasang-tugas-3 | Tidak ada | Aturan statis per kategori di app |
| X14 | Chip nominal cepat (75rb…300rb, +10rb, Rp50rb/100rb) | pasang-tugas-3, ubah-tugas, isi-saldo, tarik-saldo | Tidak perlu | UI-only (boleh diturunkan dari acuan harga kategori) |
| X15 | "Estimasi tiba: Hari ini maks. 23:59", "±5-15 menit", "Biaya admin Rp0" | tarik-saldo, isi-saldo | SLA manual | Janji SLA; sepakati dengan tim pengelola |
| X16 | "Berjalan tepat waktu" | detail-tugas-sedang-dikerjakan | Bisa diturunkan dari `needed_at` vs `arrived_at` | UI-only |
| X17 | "Bonus Pengguna Baru Rp25.000" | saldo | Bisa lewat `adjustment_credit`, tapi tidak ada program | Promo ada atau tidak? |
| X18 | Chat / "Grup Chat" | detail-* | Placeholder lokal; diputuskan DB terpisah (`docs/API.md` "Belum ada") | Di luar cakupan API ini |
| X19 | Tombol "share", saran POI di peta, "Bahasa Aplikasi", versi app | detail-*, pilih-lokasi, akun | Tidak perlu | Share link web (opsional); POI dari geocoder klien; bahasa & versi lokal |
| X20 | Kartu "Garansi Amanah", "Kategori Populer", "~28 mitra aktif" (UX Enh. #8, #14) | beranda, pasang-tugas | Tidak ada | Banner statis; hitungan mitra aktif butuh B13/U13 |

---

### Catatan untuk implementasi
- Urutan disarankan: **U2 → U1 → B1 → B2** (P0), lalu U3/U4 (dompet), U6/U7/B4/B5 (ulasan & profil), B6, B3, U12/U13, U8–U11.
- U2 menyentuh batas pengungkapan: tambahkan test disclosure seperti yang sudah ada untuk NIK/nomor rekening.
- Semua rute baru: FormRequest → DTO → Action → Resource, cursor pagination, didaftarkan di `openapi.yaml` + tabel ringkasan `API.md` (dijaga `ApiDocumentationTest`).
- Setelah BE siap, perbarui skill mobile `references/arch/api-*.md` (terutama catatan "Belum ada endpoint alamat" dan "Pendapatan masih salah hitung").

---

## 5. Status implementasi (2026-09-26)

Terpasang di working tree `dev/miftah` (API + test + dokumentasi), kecuali yang
disebut di bawah:

| Kelompok | Item | Status |
|---|---|---|
| Ubah (P0) | U2 (lokasi tersamar + `tasks.area`) | selesai |
| Ubah (P1) | U3, U4, U5, U6, U7, U8, U9, U10, U11, U12, U13 | selesai |
| Baru (P0) | B2 (ringkasan saldo) | selesai |
| Baru (P1) | B3, B4, B5, B6 | selesai |
| Ubah (P0) | **U1 (login nomor HP)** | belum — bila produk memilih copy "Email atau username", tidak perlu BE |
| Baru (P0) | **B1 (konfigurasi dompet)** | belum — menunggu nomor rekening tujuan yang SAH; diisi dari env dan wajib dicek manusia |
| Ubah (P2) | U14–U17 | belum |
| Baru (P2) | B7–B17 | belum |
| UI-only | X1–X20 | keputusan produk |

Bukti: `docs/openapi.yaml` + `docs/API.md` (104 operation = 104 rute),
`database/schema/sekarya-install.sql` dibangun ulang, suite penuh hijau
(dijaga `ApiDocumentationTest`, `InstallSchemaTest`,
`WorkerAggregateMigrationTest`).
