<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Bawaan kolomnya `Suspended`, bukan `Active` — lihat migrasi `admins`.
 *
 * Satu method saja yang menjawab "boleh?", dipakai di DUA lapis: saat login,
 * dan di setiap permintaan sesudahnya (middleware EnsureActiveAdmin). Dua lapis
 * karena token berumur delapan jam: pengelola yang dinonaktifkan setelah masuk
 * masih memegang token yang sah sampai kedaluwarsa.
 */
enum AdminStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
