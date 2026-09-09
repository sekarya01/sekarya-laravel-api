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
    ],

];
