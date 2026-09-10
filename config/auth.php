<?php

use App\Models\Admin;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    /*
    |--------------------------------------------------------------------------
    | DUA POPULASI PEMILIK TOKEN — PROVIDER SETIAP GUARD WAJIB DISEBUT
    |--------------------------------------------------------------------------
    |
    | Kalau guard `sanctum` TIDAK ada di berkas ini, Sanctum mendaftarkannya
    | sendiri saat runtime dengan `provider => null`
    | (SanctumServiceProvider::register). Dan dengan provider null,
    | Laravel\Sanctum\Guard::hasValidProvider() mengembalikan true tanpa
    | memeriksa apa pun:
    |
    |     if (is_null($this->provider)) { return true; }
    |
    | Artinya guard itu menerima pemilik token JENIS APA PUN. Selama hanya ada
    | satu model bertoken (User) itu tidak terasa. Begitu ada `admins`, token
    | pengelola langsung sah di seluruh endpoint pengguna, dan token pengguna
    | sah di seluruh `/admin` — tanpa galat, tanpa jejak, tanpa satu baris kode
    | pun yang salah.
    |
    | Dua blok di bawah inilah yang menutupnya, dan keduanya harus tetap
    | menyebut `provider`. Lapis keduanya ada di ability token
    | (`token:access` vs `admin:access`, lihat App\Enums\TokenAbility), supaya
    | satu baris yang hilang di sini tidak langsung berarti kebocoran.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // Pengguna aplikasi. Hanya menerima token milik App\Models\User.
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],

        // Pengelola. Hanya menerima token milik App\Models\Admin.
        'admin' => [
            'driver' => 'sanctum',
            'provider' => 'admins',
        ],

        // Dasbor web super_admin (server-rendered, /access/super_admin).
        // Sesi, bukan token: browser memegang cookie HttpOnly, bukan Bearer
        // di localStorage. Provider sama dengan guard `admin` (tabel admins),
        // sehingga populasinya tetap terpisah dari `users`.
        'admin_web' => [
            'driver' => 'session',
            'provider' => 'admins',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // Pengelola. Tabel dan model terpisah — bukan `users` yang disaring
        // peran, karena `users` punya jalur tulis publik dan tabel ini tidak.
        'admins' => [
            'driver' => 'eloquent',
            'model' => Admin::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
