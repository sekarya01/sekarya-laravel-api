# Observability — Axiom

Seluruh aplikasi mengirim satu aliran event terstruktur ke [app.axiom.co].
Berkas ini menjelaskan **apa yang dikirim**, **apa yang tidak pernah dikirim dan
kenapa**, cara menyalakannya, dan cara membuktikan bahwa penyaringannya bekerja.

SSOT untuk semua keputusannya ada di `config/axiom.php`. Kalau berkas ini dan
config itu berbeda, config yang benar — dan berkas ini yang harus diperbaiki.

---

## Menyalakan

```bash
# app.axiom.co → Settings → API Tokens → Create token
#   scope: dataset "sekarya", izin: ingest saja. Bukan personal token.
AXIOM_ENABLED=true
AXIOM_TOKEN=xaat-...          # jangan pernah masuk git
AXIOM_DATASET=sekarya
AXIOM_ORG_ID=                 # hanya untuk personal token

# Kirim juga Log::* aplikasi. JANGAN hilangkan `single`.
LOG_STACK=single,axiom
```

Lalu buktikan, jangan diasumsikan:

```bash
php artisan sekarya:axiom            # status konfigurasi (token tidak dicetak)
php artisan sekarya:axiom --audit    # LIHAT hasil penyaringan, tanpa mengirim
php artisan sekarya:axiom --ping     # kirim satu event uji
```

`--ping` mencetak `request_id`-nya. Cari di Axiom:

```kusto
['sekarya'] | where event == 'axiom.ping'
```

Kalau tidak terkirim, alasannya ada di `storage/logs/laravel.log` dengan awalan
`[axiom]`. Kegagalan Axiom tidak pernah menjadi galat bagi pengguna.

> **Satu blok `AXIOM_*` saja di `.env`.** Dotenv memakai kemunculan **terakhir**,
> jadi sebuah `AXIOM_TOKEN=` kosong yang tertinggal di bawah akan menimpa token
> yang sudah benar di atasnya. Gejalanya menyesatkan: `[axiom] kredensial Axiom
> belum diisi`, padahal tokennya jelas terlihat di berkas. `php artisan
> sekarya:axiom` sekarang menolak diam soal ini dan menyebutkan kunci yang ganda.

> `AXIOM_ENABLED` default **false**. Aplikasi harus jalan normal tanpa Axiom, dan
> tidak boleh ada pengiriman keluar yang aktif hanya karena lupa. Di test ia
> dimatikan keras lewat `phpunit.xml` — 718 test berjalan dengan payload berisi
> kata sandi dan kode verifikasi, dan sekali suite jalan dengan Axiom hidup,
> semuanya terkirim ke pihak ketiga tanpa bisa ditarik kembali.

---

## Event yang dikirim

Satu baris per event, semuanya membawa `service`, `env`, `host`, `release`,
`level`, `kind`, `event`, `request_id`, dan `user_id` bila ada.

| `event` | Kapan | Kolom penting |
|---|---|---|
| `http.request` | setiap request API | `http.method/path/route/status/duration_ms/slow`, `http.query/body/files/headers`, `error` bila ≥400 |
| `security.unauthenticated` | 401 | `http.route`, `ip_prefix` |
| `security.forbidden` | 403 | idem |
| `security.validation_failed` | 422 | idem |
| `security.rate_limited` | 429 | idem |
| `auth.login` / `auth.login_failed` | hasil `POST /auth/login` | `auth.reason` (`invalid_credentials`, `account_not_active`, …) |
| `auth.registered`, `auth.email_verified`, `auth.verify_failed`, `auth.code_resent`, `auth.token_refreshed`, `auth.refresh_failed`, `auth.logout` | rute auth lain | `auth.status`, `auth.reason` |
| `error.exception` | exception tak tertangani | `error.type/message/file/line/trace/previous` |
| `error.domain` | pelanggaran aturan bisnis | `error.domain_code`, `error.context` |
| `error.http` | 404/405 dan sejenisnya | `error.http_status` |
| `error.fatal` | galat fatal PHP (memori habis, timeout) | `error.type/message/file/line` |
| `log.record` | setiap `Log::*` | `log.channel/message/context` |
| `db.slow_query` | kueri > `AXIOM_SLOW_QUERY_MS` | `db.sql` (tanpa bindings), `db.time_ms`, `db.bindings_count` |
| `outbound.response` | panggilan HTTP keluar | `outbound.host/path/status/duration_ms` |
| `outbound.connection_failed` | gateway tidak menjawab | `outbound.host`, `error.message` |
| `job.queued` / `job.processing` / `job.processed` / `job.failed` | siklus queue | `job.name/queue/attempts/uuid` |
| `mail.sending` | surat keluar | `mail.recipients`, `mail.to_sha`, `mail.to_domains` |
| `mail.notification_sent` / `mail.notification_failed` | notifikasi | `mail.notification/channel` |
| `console.command_failed` | perintah artisan exit ≠ 0 | `console.command/exit_code` |
| `console.scheduled_task_failed` | scheduler gagal | `console.task`, `error` |
| `axiom.dropped` | buffer penuh / penyaringan gagal | `dropped` |

Tiap kelompok bisa dimatikan sendiri lewat `AXIOM_CAPTURE_*` (lihat
`config/axiom.php`). Mematikan sebuah kelompok berarti listener-nya **tidak
dipasang sama sekali** — penting untuk `slow_queries`, yang dipanggil pada
setiap kueri.

---

## Yang TIDAK pernah dikirim

Ini bagian yang tidak boleh "dirapikan". Setiap barisnya menutup kebocoran nyata
yang mungkin terjadi di aplikasi ini.

| Tidak dikirim | Kenapa |
|---|---|
| Kata sandi, `password_confirmation` | kredensial |
| **Kode verifikasi** (`code`) | enam angka yang bisa mengaktifkan akun orang lain |
| **Subjek e-mail** | subjeknya berbunyi "Kode verifikasi Sekarya: 623862" — ia *memuat* kredensialnya |
| Token Sanctum, header `Authorization`, `Cookie` | menyamar sebagai pengguna |
| NIK, NPWP, nomor rekening, `*_enc`, `*_hash` | identitas pemerintah & keuangan |
| Path foto KTP / selfie, `gateway_payload` | artefak identitas |
| **Nama berkas yang diunggah** | "KTP_Budi_Prasetyo.jpg" menyebut orangnya lewat metadata |
| **Bindings kueri** | di situlah nilainya berada: e-mail pada `where email = ?`, NIK pada pencarian duplikat |
| **Argumen di stack trace** | `getTraceAsString()` menyertakan argumen skalar, jadi `Hash::check('rahasia', …)` muncul apa adanya |
| **Body respons sukses** | isinya data pengguna; nilai debug-nya tidak sepadan |
| Argumen perintah artisan | `artisan user:reset --password=…` |
| Path absolut | membocorkan struktur direktori dan nama pengguna sistem |
| **Path panggilan ke `api.pwnedpasswords.com`** | `Password::uncompromised()` memanggil `/range/46B35`, dan lima karakter itu adalah awalan SHA-1 **kata sandi** pengguna |

Yang dikirim sebagai **pseudonim**, bukan dibuang — supaya korelasi tetap
mungkin: alamat e-mail, nomor telepon, nama orang, IP.

```
budi.prasetyo@contoh.test  →  email_sha=92c792f9403f3a86  email_domain=contoh.test
+628111222333              →  phone_sha=b70cf8bd9b24a309
203.0.113.42               →  ip_prefix=203.0.113.0/24    ip_sha=…
```

Pseudonimnya HMAC-SHA256 berkunci `APP_KEY`, dipotong 16 hex. Hash telanjang
tidak cukup: ruang tebakan sebuah alamat e-mail atau nomor HP Indonesia kecil,
jadi SHA256 tanpa kunci bisa dibalik dengan kamus. `APP_KEY` tidak pernah ada di
Axiom, jadi pseudonimnya hanya bisa dicocokkan kembali dari dalam server ini.

Teks bebas milik pengguna (`bio`, `description`, `title`, `address_line`)
dikirim sebagai `[len:N]` — bentuk dan ukurannya berguna saat men-debug
validasi, isinya tidak.

### Tiga lapis, dan ketiganya diperlukan

1. **Nama kunci** — denylist. Cepat dan tegas, tapi hanya sekuat kelengkapan
   daftarnya.
2. **Pola nilai** — token Sanctum, JWT, `Bearer`/`Basic`, kunci PEM, `base64:`,
   alamat e-mail, nomor HP, 16 angka berurutan, rahasia di query string,
   kredensial di URL, blob panjang. **Ini yang menutup lubang lapis 1**: setiap
   kali skema payload berubah, denylist tertinggal — pola nilai tidak.
3. **Batas ukuran** — panjang string, kedalaman, jumlah elemen. Event besar
   hampir selalu berarti ada data mentah terbawa.

Header memakai **allowlist**, bukan denylist: `Authorization` dan `Cookie` ada di
setiap request terautentikasi, dan satu kelalaian denylist langsung berarti token
pengguna terkirim ke pihak ketiga.

### Membuktikannya

```bash
php artisan sekarya:axiom --audit
```

Mencetak payload sintetis berisi setiap jenis data berbahaya di aplikasi ini,
sebelum dan sesudah penyaringan. **Jalankan setiap kali `config/axiom.php`
disentuh dan setiap kali sebuah endpoint menerima field baru.**

Jaring pengamannya di test:

```bash
php artisan test tests/Unit/Logging tests/Feature/Api/V1/AxiomObservabilityTest.php
```

`AxiomObservabilityTest` menjalankan trafik API sungguhan lalu menegaskan bahwa
kata sandi, alamat e-mail, nomor telepon, nama, dan token yang baru diterbitkan
**tidak ada** di payload yang benar-benar terkirim.

---

## Melacak satu request

Setiap respons membawa `X-Request-Id`, dan setiap event dari request itu memakai
id yang sama.

```kusto
['sekarya'] | where request_id == '01M21JSGAETJGA3DY1PE4BDJCT' | sort by _time asc
```

Klien boleh mengirim `X-Request-Id` sendiri (`^[A-Za-z0-9._-]{8,64}$`) supaya
satu jejak menyatu melewati beberapa layanan.

### Kueri yang sering dipakai

```kusto
// Endpoint paling lambat
['sekarya']
| where event == 'http.request'
| summarize p95 = percentile(http.duration_ms, 95), n = count() by http.route
| sort by p95 desc

// Galat sungguhan saja — pelanggaran aturan bisnis tidak ikut
['sekarya'] | where event == 'error.exception' | sort by _time desc

// Percobaan pembajakan: satu pseudonim, banyak kegagalan
['sekarya']
| where event == 'auth.login_failed'
| summarize percobaan = count() by auth.identifier_sha, auth.ip_prefix
| where percobaan > 10

// Kueri lambat, dikelompokkan per bentuk SQL
['sekarya']
| where event == 'db.slow_query'
| summarize n = count(), p95 = percentile(db.time_ms, 95) by db.sql
| sort by p95 desc

// Kesehatan gateway pembayaran
['sekarya'] | where kind == 'outbound' | summarize count() by outbound.host, outbound.status
```

---

## Biaya dan kebisingan

- `AXIOM_SAMPLE_SUCCESS` (default `1.0`) membuang sebagian trafik sukses.
  **Request gagal dan request lambat tidak pernah di-sample** — sampling yang
  ikut membuang error akan menghapus justru satu-satunya bukti dari kejadian
  yang jarang, dan yang jarang itulah yang di-debug.
- `axiom.ignore_paths` menutup `/up` dan `/docs`. Health check dipanggil tiap
  beberapa detik oleh load balancer.
- `AXIOM_SLOW_QUERY_MS` (default 200) menahan `db.slow_query` agar tidak menjadi
  satu event per kueri.
- `AXIOM_MAX_BATCH` (default 500) membatasi memori. Kelebihannya dibuang dan
  dilaporkan sebagai `axiom.dropped` — lubangnya terlihat, bukan hilang diam-diam.

## Latensi

`AXIOM_DELIVERY=sync` (default) mengirim satu batch saat request selesai
(`terminate`), dengan timeout 3 detik. `AXIOM_DELIVERY=queue` menitipkannya ke
worker sehingga request tidak menunggu jaringan sama sekali; harganya, log baru
muncul setelah worker mengambilnya.

Apa pun pilihannya, kegagalan pengiriman **tidak pernah** menggagalkan request —
ia dicatat ke `storage/logs/laravel.log` dan diabaikan.

---

## Cara kerjanya

```
                    ┌──────────────────────────────────────┐
request ──────────► │ AxiomRequestLogger (middleware)      │ handle: markStart, request_id
                    │  terminate: http.* + security.*      │
                    │             + auth.*  → flush()      │
                    └───────────────┬──────────────────────┘
Log::*  ─► AxiomHandler ────────────┤
exception ─► ExceptionRecorder ─────┤
event Laravel ─► AxiomServiceProvider ┤     ┌─────────────┐    ┌────────────┐
(auth/job/query/mail/http/console)  └────►  │ AxiomLogger │───►│ AxiomClient│──► api.axiom.co
                                            │  (buffer)   │    └────────────┘
                                            └──────┬──────┘
                                            Redactor (3 lapis)
```

Catatan yang menghemat waktu orang berikutnya:

- **`AxiomLogger` wajib singleton.** Satu buffer per proses; kalau tidak, tiap
  sumber event mengirim batch sendiri dan `request_id` yang menyatukan mereka
  jadi berbeda-beda — jejak satu request pecah menjadi puluhan baris terpisah.
- **Waktu mulai request disimpan di `AxiomLogger`, bukan di middleware.** Laravel
  membuat instance middleware **baru** untuk memanggil `terminate()`, jadi
  properti instance tidak selamat dari `handle()` ke `terminate()`. Gejalanya
  tidak kentara: durasi terhitung sejak epoch, setiap request lolos ambang
  "lambat", dan karena request lambat tidak pernah di-sample, sampling diam-diam
  berhenti bekerja.
- **Middleware dipasang paling luar pada grup `api`.** Di dalam, request yang
  ditolak `throttle` (429) atau `auth:sanctum` (401) tidak akan pernah tercatat —
  padahal itu event yang paling dibutuhkan saat menyelidiki abuse.
- **Aplikasi ini tidak pernah memanggil `Auth::attempt()`.** `LoginAction`
  memverifikasi hash sendiri, jadi `Illuminate\Auth\Events\Login`/`Failed` tidak
  pernah menyala. Catatan auth diturunkan dari nama rute + status
  (`axiom.auth_routes`). Listener bawaan tetap dipasang untuk jalur berbasis
  guard yang mungkin ditambahkan nanti — jangan hapus jalur turunan itu dengan
  anggapan listener menggantikannya.
- **Path panggilan keluar bisa jadi rahasia itu sendiri.** Daftar host-nya di
  `axiom.redact_outbound_path_for`. Yang sekarang ada di sana ditemukan dengan
  MENJALANKAN pendaftaran sungguhan melalui penangkap payload, bukan dari
  membaca kode — `api.pwnedpasswords.com/range/46B35` terlihat seperti metadata
  biasa dan lolos setiap penyaring berbasis nama kunci maupun pola nilai.
  Tambahkan host baru ke daftar itu setiap kali aplikasi memanggil layanan yang
  meletakkan nilai turunan rahasia di dalam URL.
- **Tidak ada package pihak ketiga.** Monolog, Guzzle, dan HTTP client Laravel
  sudah ada; menulis handler sendiri memberi kendali penuh atas apa yang keluar,
  dan itu justru inti masalahnya di sini.
