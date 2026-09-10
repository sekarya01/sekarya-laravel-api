<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kemampuan yang dilekatkan pada token Sanctum.
 *
 * Pemisahan ini adalah inti keamanannya: `auth:sanctum` saja hanya membuktikan
 * "token ini sah", BUKAN "token ini boleh memanggil endpoint aplikasi".
 * Tanpa pengecekan ability, long_lived token bisa dipakai langsung untuk
 * mengakses seluruh API — dan umurnya panjang, jadi pencuriannya fatal.
 *
 * Sejak ada tabel `admins`, enum ini juga yang memisahkan DUA POPULASI token.
 * Pemisah pertamanya adalah guard (`auth:sanctum` untuk pengguna, `auth:admin`
 * untuk pengelola) yang provider-nya disebut eksplisit di `config/auth.php`.
 * Ability di sini adalah lapis KEDUA-nya, dan lapis itu perlu karena lapis
 * pertama bisa hilang tanpa suara: Sanctum mendaftarkan guard `sanctum`
 * sendiri dengan `provider => null` kalau config tidak menyebutkannya, dan
 * dengan provider null `Guard::hasValidProvider()` meloloskan pemilik token
 * jenis APA PUN. Satu baris config yang hilang cukup untuk membuat token
 * pengguna berlaku di seluruh `/admin`. Dengan ability terpisah, token
 * pengguna tetap ditolak karena ia tidak pernah membawa `admin:access`.
 */
enum TokenAbility: string
{
    /** Untuk memanggil endpoint aplikasi. Umur pendek. */
    case Access = 'token:access';

    /** HANYA untuk menukar diri jadi access token baru. */
    case Refresh = 'token:refresh';

    /** Untuk memanggil endpoint `/admin`. Umur pendek. */
    case AdminAccess = 'admin:access';

    /** HANYA untuk menukar diri jadi admin access token baru. */
    case AdminRefresh = 'admin:refresh';
}
