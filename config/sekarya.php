<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Token
    |--------------------------------------------------------------------------
    |
    | Dua jenis token dengan peran berbeda:
    |
    |  - access       dipakai untuk SEMUA panggilan API. Umur pendek.
    |  - long_lived   HANYA dipakai untuk menukar diri jadi access token baru.
    |                 Tidak bisa memanggil endpoint aplikasi apa pun.
    |
    | Pemisahan ini yang membuat pencurian access token terbatas dampaknya:
    | ia mati sendiri dalam beberapa jam, dan pemegangnya tidak bisa
    | memperpanjang tanpa long_lived token.
    |
    */

    'tokens' => [
        // Sesuai ketentuan: access token kedaluwarsa 8 jam sejak dibuat.
        'access_ttl_hours' => (int) env('SEKARYA_ACCESS_TTL_HOURS', 8),

        // Umur long_lived token. Sesudah ini pengguna harus login ulang.
        'long_lived_ttl_days' => (int) env('SEKARYA_LONG_LIVED_TTL_DAYS', 30),

        // Nama token di tabel personal_access_tokens.
        'access_name' => 'access',
        'long_lived_name' => 'long_lived',
    ],

    /*
    |--------------------------------------------------------------------------
    | Verifikasi email
    |--------------------------------------------------------------------------
    |
    | Akun baru TIDAK langsung aktif. Kode dikirim ke email dan harus
    | dimasukkan lebih dulu.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Auth
    |--------------------------------------------------------------------------
    */

    'auth' => [
        // Cek DNS domain email saat pendaftaran.
        //
        // Bermanfaat di produksi: seluruh alur bergantung pada email yang
        // benar-benar bisa dihubungi, jadi domain salah tulis lebih baik
        // ditolak di depan.
        //
        // Harganya: pendaftaran jadi bergantung pada lookup DNS — menambah
        // latensi dan bisa menolak email sah saat DNS bermasalah. Dimatikan
        // di lokal supaya domain uji seperti `.test` bisa dipakai.
        'validate_email_dns' => (bool) env('SEKARYA_VALIDATE_EMAIL_DNS', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Profil
    |--------------------------------------------------------------------------
    |
    | Batas umur. Angkanya di sini, bukan tersebar sebagai literal di aturan
    | validasi — dua tempat yang menuliskannya sendiri-sendiri akan melenceng,
    | dan yang melenceng adalah syarat siapa yang boleh bekerja.
    |
    */

    'profile' => [
        // 17 tahun: usia KTP di Indonesia. Verifikasi identitas di aplikasi ini
        // mencocokkan dengan KTP, jadi orang yang belum bisa punya KTP tidak
        // akan pernah bisa lolos verifikasi.
        'min_age' => (int) env('SEKARYA_MIN_AGE', 17),

        // Batas atas yang masuk akal. Bukan aturan bisnis — penjaga salah
        // ketik: tahun 1025 lolos sebagai tanggal yang sah.
        'max_age' => (int) env('SEKARYA_MAX_AGE', 100),
    ],

    'verification' => [
        // Panjang kode numerik. 6 angka = 1 juta kemungkinan; yang menjaganya
        // bukan panjangnya, tapi masa berlaku pendek + batas percobaan.
        'code_length' => 6,

        'ttl_minutes' => (int) env('SEKARYA_VERIFICATION_TTL_MINUTES', 15),

        // Percobaan salah per kode sebelum kode itu dibatalkan. Batas ini
        // berlaku pada KODE, bukan pada IP — jadi rotasi IP tidak menolong
        // penyerang.
        'max_attempts' => 5,

        // Jarak minimum antar permintaan kirim ulang.
        'resend_cooldown_seconds' => (int) env('SEKARYA_RESEND_COOLDOWN_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pengelola (super_admin & admin)
    |--------------------------------------------------------------------------
    |
    | Akun super_admin TIDAK ada di seeder maupun di berkas pemasangan SQL.
    | Keduanya dilacak git, jadi kredensial di dalamnya bukan kredensial —
    | ia sandi bawaan yang bisa dibaca siapa pun yang membuka repositori, di
    | akun yang paling berhak di seluruh aplikasi.
    |
    | Karena itu nilainya diambil dari environment dan akunnya dibuat sekali
    | dengan `php artisan sekarya:admin create`. Di produksi perintah itu
    | MENOLAK sandi yang sama dengan contoh di `.env.example`.
    |
    */

    'admin' => [
        'super_admin' => [
            'name' => env('SEKARYA_SUPER_ADMIN_NAME', 'Super Admin'),
            'email' => env('SEKARYA_SUPER_ADMIN_EMAIL'),
            'password' => env('SEKARYA_SUPER_ADMIN_PASSWORD'),
        ],

        // Sandi pengelola dipisahkan dari sandi pengguna: yang dijaga bukan
        // satu akun, tapi kewenangan menyetujui uang.
        'min_password_length' => 12,

        // Sandi contoh yang ikut terlacak git. Ditolak di produksi — daftar
        // ini yang membuat penolakannya bisa diuji, bukan diingat.
        'forbidden_passwords' => [
            'SuperAdminSekarya2026',
            'password',
            'admin',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limit
    |--------------------------------------------------------------------------
    |
    | Angka per menit per kunci. Endpoint auth dibatasi jauh lebih ketat
    | daripada endpoint aplikasi karena di situlah percobaan tebak kata sandi
    | dan tebak kode verifikasi terjadi.
    |
    | Kunci pembatas didefinisikan di RateLimitServiceProvider.
    |
    */

    'rate_limits' => [
        // Panggilan API biasa, per pengguna terautentikasi.
        'api' => (int) env('SEKARYA_RL_API', 120),

        // Login: per gabungan email + IP, supaya satu penyerang tidak bisa
        // mengunci akun orang lain hanya dengan membanjiri percobaan.
        'login' => (int) env('SEKARYA_RL_LOGIN', 5),

        // Pendaftaran, per IP.
        'register' => (int) env('SEKARYA_RL_REGISTER', 5),

        // Cek ketersediaan email/username/phone sebelum daftar, per IP.
        'availability' => (int) env('SEKARYA_RL_AVAILABILITY', 10),

        // Memasukkan kode verifikasi, per email + IP.
        'verify' => (int) env('SEKARYA_RL_VERIFY', 6),

        // Minta kirim ulang kode, per email.
        'resend' => (int) env('SEKARYA_RL_RESEND', 3),

        // Minta tautan reset kata sandi, per email. Sama ketatnya dengan
        // resend: mencegah pembanjiran inbox orang lain dari banyak IP.
        'forgot' => (int) env('SEKARYA_RL_FORGOT', 3),

        // Memakai tautan reset (buka form maupun submit), per email + IP.
        // Longgar seperti verify: pemilik sah boleh salah ketik beberapa kali.
        'reset' => (int) env('SEKARYA_RL_RESET', 6),

        // Tukar long_lived token jadi access token baru, per pengguna.
        'refresh' => (int) env('SEKARYA_RL_REFRESH', 10),

        // Membuat task & penawaran — mencegah spam yang membanjiri feed.
        'write' => (int) env('SEKARYA_RL_WRITE', 30),

        // Endpoint pengelola, per pengelola. Lebih longgar dari `api`:
        // menilai antrean verifikasi berarti membuka banyak halaman
        // berturut-turut, dan yang memakainya cuma beberapa akun internal.
        'admin' => (int) env('SEKARYA_RL_ADMIN', 240),

        // Login pengelola. JAUH lebih ketat daripada login pengguna: yang
        // dijaga di sini bukan satu akun belanja, tapi akun yang bisa
        // menyetujui pembayaran. Kuncinya email + IP, sama alasannya seperti
        // login pengguna.
        'admin_login' => (int) env('SEKARYA_RL_ADMIN_LOGIN', 5),
    ],

];
