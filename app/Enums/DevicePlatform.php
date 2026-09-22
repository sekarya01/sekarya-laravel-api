<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Platform asal sebuah token perangkat.
 *
 * Disimpan, bukan sekadar diterima lalu dibuang: FCM memerlukan konfigurasi
 * yang berbeda per platform (kanal Android vs payload APNs), dan menyimpannya
 * membuat pengiriman tidak perlu menebak dari bentuk token.
 */
enum DevicePlatform: string
{
    case Android = 'android';
    case Ios = 'ios';
}
