# Berkontribusi

## Pesan commit — Conventional Commits

Format:

```
<tipe>(<cakupan>): <ringkasan>

<badan, opsional>

<footer, opsional>
```

Aturannya dipilih supaya riwayat bisa dibaca sebagai daftar keputusan, bukan daftar
perubahan berkas. `git log --oneline` harus cukup untuk menjawab "apa yang berubah di
rilis ini dan kenapa".

### Tipe

| Tipe | Dipakai untuk |
|---|---|
| `feat` | Kemampuan baru yang terlihat dari luar — endpoint, filter, aturan bisnis |
| `fix` | Perbaikan perilaku yang salah |
| `perf` | Perubahan yang tujuannya kecepatan, dengan angka sebelum/sesudah di badan |
| `refactor` | Bentuk kode berubah, perilaku tidak |
| `test` | Menambah atau memperbaiki test saja |
| `docs` | Dokumentasi saja — termasuk `docs/openapi.yaml` |
| `build` | Dependensi, `composer.json`, berkas build |
| `chore` | Sisanya: konfigurasi alat, `.gitignore`, berkas rumah tangga |

### Cakupan

Sebutkan **lapisan atau domainnya**, bukan nama berkas: `auth`, `task`, `bid`, `payment`,
`activity`, `review`, `search`, `observability`, `docs`, `db`. Boleh dikosongkan kalau
perubahannya menyentuh banyak tempat.

### Ringkasan

- Bahasa Indonesia, huruf kecil di awal, tanpa titik di akhir.
- Kalimat perintah: "tambah filter waktu di feed", bukan "menambahkan" atau "ditambahkan".
- Maksimal 72 karakter.
- Sebut **hasilnya**, bukan mekanismenya. `feat(search): temukan nama dua huruf seperti "AC"`
  lebih berguna daripada `feat(search): tambah kolom sentinel`.

### Badan

Wajib kalau perubahannya punya alasan yang tidak terbaca dari diff-nya. Jawab **kenapa**,
bukan apa — apanya sudah ada di diff. Kalau sebuah keputusan punya harga, tuliskan
harganya.

### Footer

- `BREAKING CHANGE: <penjelasan>` untuk perubahan yang memaksa klien menyesuaikan diri.
  Wajib disertai apa yang harus diubah klien.
- `Refs: #123` untuk isu terkait.

### Contoh

```
feat(task): satu task bisa merekrut banyak pekerja

`workers_needed` menentukan berapa orang yang diterima. Lelangnya tetap
terbuka — pelamarnya boleh jauh lebih banyak daripada slotnya, dan pemberi
kerja memilih berdasarkan harga penawaran.

Status task mengikuti agregat seluruh pekerja: dana dilepas hanya ketika
pekerja terakhir disetujui. Melepasnya pada persetujuan pertama akan
mengeluarkan seluruh tagihan untuk satu orang.

BREAKING CHANGE: `POST /tasks/{task}/payment/hold` mengembalikan DAFTAR
activity, satu per pekerja. Klien yang membaca `data.id` harus membaca
`data[0].id`.
```

```
fix(observability): hitung durasi request dari singleton, bukan middleware

Laravel membuat instance middleware baru untuk memanggil `terminate()`, jadi
waktu mulai yang disimpan sebagai properti instance hilang. Durasi terhitung
sejak epoch, setiap request lolos ambang "lambat", dan karena request lambat
tidak pernah di-sample, sampling berhenti bekerja tanpa satu pun tanda.
```

## Sebelum commit

```bash
./vendor/bin/pint                 # format
php artisan test                  # seluruh suite
composer test-report              # + coverage
bash docs/smoke.sh                # HTTP sungguhan, server sendiri
npx --yes -p @redocly/cli redocly lint docs/openapi.yaml
```

Menambah endpoint tanpa mencatatnya di `docs/openapi.yaml` **membuat suite gagal** —
lihat `tests/Feature/Docs/ApiDocumentationTest.php`. Itu disengaja: spesifikasi di proyek
ini adalah kontrak, bukan produk sampingan.

## Yang tidak boleh masuk repositori

`.env` sudah di-gitignore dan harus tetap begitu. Jangan pernah menaruh token, kunci API,
atau data pengguna sungguhan di berkas yang dilacak — termasuk di fixture test dan contoh
di dokumentasi. Contoh memakai domain `.test` dan nilai yang jelas palsu.
