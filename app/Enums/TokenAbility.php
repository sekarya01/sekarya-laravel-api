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
 */
enum TokenAbility: string
{
    /** Untuk memanggil endpoint aplikasi. Umur pendek. */
    case Access = 'token:access';

    /** HANYA untuk menukar diri jadi access token baru. */
    case Refresh = 'token:refresh';
}
