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

        // Memasukkan kode verifikasi, per email + IP.
        'verify' => (int) env('SEKARYA_RL_VERIFY', 6),

        // Minta kirim ulang kode, per email.
        'resend' => (int) env('SEKARYA_RL_RESEND', 3),

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
