<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Axiom (app.axiom.co) — observability
|------------------------------------------------------------------------------
|
| SSOT untuk SEMUA yang dikirim keluar aplikasi. Satu berkas, karena keputusan
| "apa yang boleh keluar" adalah keputusan keamanan: kalau tersebar di sepuluh
| tempat, tidak ada yang bisa mengauditnya.
|
| Prinsip yang dipegang berkas ini:
|
|  1. Yang dikirim hanya yang DIPAKAI untuk debugging. Bukan "semua yang ada,
|     nanti disaring di Axiom" — data yang sudah terkirim tidak bisa ditarik
|     kembali, dan Axiom bukan tempat penyimpanan PII.
|  2. Identitas dikirim sebagai PSEUDONIM (HMAC dengan APP_KEY), bukan nilai
|     aslinya. Cukup untuk mengorelasikan "orang yang sama" antar event tanpa
|     tahu siapa dia.
|  3. Header pakai ALLOWLIST, payload pakai denylist + penyaringan nilai.
|     Header punya `Authorization`/`Cookie` di tempat yang bisa diprediksi,
|     jadi allowlist aman dan paling ketat. Payload aplikasi berkembang terus,
|     jadi selain denylist kunci ada penyaring POLA NILAI (token, email, NIK,
|     nomor telepon) yang bekerja walau nama kuncinya belum pernah terlihat.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Kredensial & tujuan
    |--------------------------------------------------------------------------
    |
    | Token Axiom TIDAK pernah ditulis di berkas ini — hanya dibaca dari env.
    | `enabled` sengaja default false: aplikasi harus jalan normal tanpa Axiom,
    | dan tidak boleh ada pengiriman keluar yang aktif hanya karena lupa.
    |
    */

    'enabled' => (bool) env('AXIOM_ENABLED', false),

    'token' => env('AXIOM_TOKEN'),
    'dataset' => env('AXIOM_DATASET', 'sekarya'),

    // Hanya perlu untuk personal token (bukan API token dataset-scoped).
    'org_id' => env('AXIOM_ORG_ID'),

    'endpoint' => env('AXIOM_ENDPOINT', 'https://api.axiom.co'),

    /*
    |--------------------------------------------------------------------------
    | Identitas layanan — kolom yang selalu ikut di setiap event
    |--------------------------------------------------------------------------
    */

    'service' => env('AXIOM_SERVICE', 'sekarya-api'),

    // Dipakai untuk menjawab "versi mana yang error?" — isi dari CI (git SHA).
    'release' => env('AXIOM_RELEASE'),

    /*
    |--------------------------------------------------------------------------
    | Pengiriman
    |--------------------------------------------------------------------------
    |
    | Event ditumpuk di memori lalu dikirim SEKALI saat request selesai
    | (terminate), bukan satu HTTP call per event.
    |
    | delivery:
    |   sync  — dikirim di proses yang sama saat terminate. Timeout pendek.
    |   queue — dititipkan ke queue; request tidak menunggu jaringan sama sekali.
    |           Pilih ini kalau latensi p99 lebih penting daripada kesegeraan log.
    |
    | Kegagalan pengiriman TIDAK PERNAH boleh menggagalkan request. Semua galat
    | transport ditelan dan dicatat ke channel lokal (`fallback_channel`).
    |
    */

    'delivery' => env('AXIOM_DELIVERY', 'sync'),
    'queue' => env('AXIOM_QUEUE', 'default'),

    'timeout' => (float) env('AXIOM_TIMEOUT', 3.0),
    'connect_timeout' => (float) env('AXIOM_CONNECT_TIMEOUT', 1.5),
    'retries' => (int) env('AXIOM_RETRIES', 1),

    // Batas penumpukan di memori. Melebihi ini, event dibuang dan dihitung
    // sebagai `dropped` di event penutup — lebih baik kehilangan log daripada
    // kehabisan memori proses.
    'max_batch' => (int) env('AXIOM_MAX_BATCH', 500),

    'fallback_channel' => env('AXIOM_FALLBACK_CHANNEL', 'single'),

    /*
    |--------------------------------------------------------------------------
    | Event apa saja yang dikirim
    |--------------------------------------------------------------------------
    |
    | Semua bisa dimatikan sendiri-sendiri. Yang mahal (query, cache, outbound)
    | dibatasi ambang, bukan dimatikan total, supaya tetap ada bukti saat lambat.
    |
    */

    'capture' => [

        // Satu event per request HTTP: metode, rute, status, durasi, ukuran.
        'http' => (bool) env('AXIOM_CAPTURE_HTTP', true),

        // Semua Log::* milik aplikasi (lewat channel Monolog `axiom`).
        'logs' => (bool) env('AXIOM_CAPTURE_LOGS', true),

        // Exception yang di-report Laravel, termasuk fatal error.
        'exceptions' => (bool) env('AXIOM_CAPTURE_EXCEPTIONS', true),

        // Login berhasil/gagal, logout. TANPA kata sandi, TANPA token.
        'auth' => (bool) env('AXIOM_CAPTURE_AUTH', true),

        // 401 / 403 / 422 / 429 — sinyal keamanan, bukan sekadar galat klien.
        'security' => (bool) env('AXIOM_CAPTURE_SECURITY', true),

        // Job queue: masuk, selesai, gagal.
        'jobs' => (bool) env('AXIOM_CAPTURE_JOBS', true),

        // Perintah artisan & scheduler yang gagal.
        'console' => (bool) env('AXIOM_CAPTURE_CONSOLE', true),

        // Query yang LEBIH LAMBAT dari ambang. Bindings tidak pernah ikut.
        'slow_queries' => (bool) env('AXIOM_CAPTURE_SLOW_QUERIES', true),

        // Panggilan HTTP keluar (gateway pembayaran, dsb) dan kegagalannya.
        'outbound_http' => (bool) env('AXIOM_CAPTURE_OUTBOUND_HTTP', true),

        // Email & notifikasi terkirim/gagal. Penerima dipseudonimkan.
        'mail' => (bool) env('AXIOM_CAPTURE_MAIL', true),
    ],

    'thresholds' => [
        // Query di atas ini dianggap lambat dan dikirim.
        'slow_query_ms' => (int) env('AXIOM_SLOW_QUERY_MS', 200),

        // Request di atas ini ditandai `slow: true`.
        'slow_request_ms' => (int) env('AXIOM_SLOW_REQUEST_MS', 1000),

        // Panggilan keluar di atas ini ditandai `slow: true`.
        'slow_outbound_ms' => (int) env('AXIOM_SLOW_OUTBOUND_MS', 2000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Sampling
    |--------------------------------------------------------------------------
    |
    | Request sukses yang membosankan tidak perlu dikirim seluruhnya; yang
    | gagal dan yang lambat SELALU dikirim (sampling 1.0 dan tidak bisa
    | diturunkan oleh kode di bawah). Ini menekan biaya tanpa menghilangkan
    | justru bagian yang dipakai untuk debugging.
    |
    */

    'sampling' => [
        'success' => (float) env('AXIOM_SAMPLE_SUCCESS', 1.0),
        'redirect' => (float) env('AXIOM_SAMPLE_REDIRECT', 1.0),
        // 4xx/5xx dan request lambat tidak di-sample: selalu 1.0.
    ],

    /*
    |--------------------------------------------------------------------------
    | PRIVASI — bagian yang tidak boleh "dirapikan"
    |--------------------------------------------------------------------------
    */

    'privacy' => [

        /*
         | Pseudonimisasi.
         |
         | Nilai identitas diganti HMAC-SHA256(nilai, APP_KEY) dipotong 16 hex.
         | Kenapa HMAC dan bukan hash biasa: e-mail dan nomor HP punya ruang
         | tebakan kecil, jadi SHA256 telanjang bisa dibalik dengan kamus.
         | Kunci HMAC-nya APP_KEY, yang tidak ada di Axiom.
         |
         | Efeknya untuk debugging: "orang yang sama" tetap terlihat sama di
         | seluruh event, tanpa satu pun alamat e-mail keluar dari server.
         */
        'pseudonymize' => (bool) env('AXIOM_PSEUDONYMIZE', true),

        // Domain e-mail ikut dikirim (bukan alamatnya). Berguna saat men-debug
        // kegagalan kirim surat per-domain; domain sendiri bukan PII.
        'keep_email_domain' => (bool) env('AXIOM_KEEP_EMAIL_DOMAIN', true),

        /*
         | IP.
         |
         |   prefix — /24 (IPv4) atau /48 (IPv6). Cukup untuk melihat pola abuse
         |            dan asal trafik, tidak menunjuk satu rumah tangga.
         |   hash   — HMAC penuh, untuk mengorelasikan tanpa mengungkap.
         |   full   — alamat lengkap. JANGAN di produksi tanpa dasar hukumnya.
         |   none   — tidak ada informasi IP sama sekali.
         |
         | Default mengirim prefix + hash: bisa menghitung "berapa IP unik"
         | dan "IP ini lagi" tanpa menyimpan alamatnya.
         */
        'ip_mode' => env('AXIOM_IP_MODE', 'prefix_hash'),

        /*
         | Isi payload request.
         |
         |   none     — tidak ada body/query yang dikirim. Paling aman, paling
         |              sulit di-debug.
         |   redacted — body & query dikirim setelah melewati Redactor (default).
         |   Tidak ada opsi "raw". Sengaja.
         */
        'request_payload' => env('AXIOM_REQUEST_PAYLOAD', 'redacted'),

        /*
         | Isi response.
         |
         | Default HANYA untuk respons galat (>=400), karena di situlah isinya
         | berguna: `{message, code, errors}` menjelaskan kenapa gagal. Body
         | respons sukses justru berisi data pengguna — tidak ada nilai debug
         | yang sebanding dengan risikonya.
         |
         |   errors_only — hanya >= 400 (default)
         |   none        — tidak pernah
         */
        'response_payload' => env('AXIOM_RESPONSE_PAYLOAD', 'errors_only'),

        /*
         | Kunci yang nilainya SELALU dibuang dan diganti "[redacted]".
         | Dicocokkan case-insensitive, sebagai SUBSTRING dari nama kunci —
         | jadi `new_password_confirmation` tertangkap oleh `password`.
         */
        'deny_keys' => [
            // kredensial
            'password', 'passwd', 'secret', 'token', 'authorization', 'auth',
            'credential', 'private_key', 'api_key', 'apikey', 'access_key',
            'signature', 'cookie', 'session', 'csrf', 'xsrf', 'bearer',
            'remember_token', 'plaintexttoken', 'plain_text_token',

            // kode sekali pakai — ini kredensial, bukan metadata
            'verification_code', 'otp', 'pin', 'code_hash',

            // identitas pemerintah & keuangan
            'nik', 'npwp', 'document_number', 'account_number', 'bank_account',
            'card_number', 'cvv', 'cvc', 'iban',

            // artefak identitas
            'id_card_photo', 'selfie_photo', 'gateway_payload',
        ],

        /*
         | Kunci yang dibuang HANYA jika namanya sama persis.
         |
         | `code` di body request adalah kode verifikasi 6 angka — sebuah
         | kredensial. `code` di body RESPONS adalah kode galat mesin
         | (`invalid_credentials`) yang justru paling berguna saat debugging.
         | Karena itu payload request memakai daftar ini, sedangkan respons
         | galat dibaca lewat jalur terpisah yang mengambil `message`, `code`
         | dan nama field `errors` secara eksplisit.
         */
        'deny_keys_exact' => [
            'code', 'codes', 'key', 'hash', 'salt',
        ],

        /*
         | Kunci yang diganti pseudonim (`<kunci>_sha`) alih-alih dibuang,
         | supaya korelasi antar event tetap mungkin.
         */
        'pseudonymize_keys' => [
            'email', 'phone', 'msisdn', 'whatsapp',
            'name_on_document', 'account_holder_name',
        ],

        /*
         | Pseudonim dengan pencocokan nama kunci yang sama persis.
         |
         | `name` di body request adalah nama orang. Sebagai substring ia akan
         | ikut mengenai `route_name`, `queue_name`, `filename` — metadata yang
         | tidak sensitif dan justru dibutuhkan, jadi pencocokannya harus persis.
         */
        'pseudonymize_keys_exact' => [
            'name', 'full_name', 'ip', 'ip_address', 'client_ip',
        ],

        /*
         | Teks bebas milik pengguna: tidak ada nilai debug pada ISINYA, tapi
         | panjang & keberadaannya kadang penting (mis. galat validasi panjang).
         | Diganti penanda `[len:N]`.
         */
        'summarize_keys' => [
            'bio', 'description', 'address_line', 'message', 'note', 'notes',
            'comment', 'content', 'body', 'title', 'reason',
        ],

        /*
         | Penyaring POLA NILAI — jaring terakhir.
         |
         | Bekerja pada setiap string yang lolos aturan kunci di atas, jadi
         | rahasia yang muncul di kunci tak terduga (mis. di pesan exception
         | atau di URL) tetap tertangkap. Ini yang membuat denylist kunci tidak
         | menjadi satu-satunya pertahanan.
         */
        'scrub_value_patterns' => (bool) env('AXIOM_SCRUB_VALUES', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Batas ukuran
    |--------------------------------------------------------------------------
    |
    | Event log yang besar itu mahal dan hampir selalu berarti ada data mentah
    | yang ikut terbawa. Batas ini memotongnya sebelum keluar.
    |
    */

    'limits' => [
        'max_string' => (int) env('AXIOM_MAX_STRING', 512),
        'max_depth' => (int) env('AXIOM_MAX_DEPTH', 4),
        'max_array_items' => (int) env('AXIOM_MAX_ARRAY_ITEMS', 50),
        'max_trace_frames' => (int) env('AXIOM_MAX_TRACE_FRAMES', 30),
        'max_sql_length' => (int) env('AXIOM_MAX_SQL', 2000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Header yang boleh ikut — ALLOWLIST
    |--------------------------------------------------------------------------
    |
    | Allowlist, bukan denylist: `Authorization` dan `Cookie` ada di setiap
    | request terautentikasi, dan satu kelalaian denylist langsung berarti
    | token pengguna terkirim ke pihak ketiga.
    */

    'allowed_headers' => [
        'accept', 'accept-encoding', 'accept-language', 'content-type',
        'content-length', 'user-agent', 'referer', 'origin',
        'x-request-id', 'traceparent', 'x-forwarded-proto',
    ],

    /*
    |--------------------------------------------------------------------------
    | Panggilan keluar yang path-nya sendiri adalah rahasia
    |--------------------------------------------------------------------------
    |
    | Untuk host di daftar ini, HANYA host dan status yang dicatat; path-nya
    | diganti "[redacted]".
    |
    | Ditemukan saat menjalankan pendaftaran sungguhan dengan penangkap payload,
    | bukan dari membaca kode: aturan validasi `Password::uncompromised()`
    | memanggil `api.pwnedpasswords.com/range/46B35`, dan lima karakter itu
    | adalah awalan SHA-1 KATA SANDI yang baru diketik pengguna. Path-nya
    | terlihat seperti metadata biasa dan lolos setiap penyaring berbasis nama
    | kunci maupun pola nilai — 5 karakter heksadesimal tidak bisa dibedakan
    | dari potongan teks lain.
    |
    | Model k-anonymity HIBP memang dirancang supaya awalan itu aman dikirim ke
    | HIBP. Mengirimnya ke pihak KETIGA lain adalah hal yang berbeda: di Axiom
    | ia berada dalam satu `request_id` dengan pseudonim penggunanya, sehingga
    | ruang tebakan kata sandi orang tertentu menyempit drastis.
    |
    */

    'redact_outbound_path_for' => [
        'api.pwnedpasswords.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Hasil autentikasi, diturunkan dari rute
    |--------------------------------------------------------------------------
    |
    | API ini TIDAK memakai `Auth::attempt()`. `LoginAction` memverifikasi hash
    | sendiri lalu menerbitkan token Sanctum, jadi `Illuminate\Auth\Events\Login`
    | dan `Failed` tidak pernah menyala di sini — listener-nya tetap terpasang
    | untuk jaga-jaga, tapi menggantungkan pencatatan auth padanya berarti tidak
    | punya catatan auth sama sekali.
    |
    | Karena itu hasil auth diturunkan dari nama rute + status responsnya. Sumber
    | yang sama dengan yang dilihat klien, dan tidak menyentuh satu baris pun
    | kode Action — yang harus tetap bersih dari urusan infrastruktur.
    |
    | Kunci: nama rute. Nilai: nama event untuk sukses (`ok`) dan gagal (`fail`).
    |
    */

    'auth_routes' => [
        'v1.auth.login' => ['ok' => 'auth.login', 'fail' => 'auth.login_failed'],
        'v1.auth.logout' => ['ok' => 'auth.logout'],
        'v1.auth.register' => ['ok' => 'auth.registered', 'fail' => 'auth.register_failed'],
        'v1.auth.verify-email' => ['ok' => 'auth.email_verified', 'fail' => 'auth.verify_failed'],
        'v1.auth.resend-code' => ['ok' => 'auth.code_resent'],
        'v1.auth.refresh' => ['ok' => 'auth.token_refreshed', 'fail' => 'auth.refresh_failed'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Path yang tidak dicatat
    |--------------------------------------------------------------------------
    |
    | Health check dipanggil tiap beberapa detik oleh load balancer; mencatatnya
    | hanya menghasilkan biaya dan menenggelamkan event yang berarti.
    */

    'ignore_paths' => [
        'up',
        'docs',
        'docs/*',
    ],
];
