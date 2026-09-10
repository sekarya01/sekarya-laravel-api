<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Jenis kelamin, mengikuti dua nilai yang tercetak di KTP.
 *
 * Nilainya bahasa Inggris seperti seluruh enum lain di aplikasi ini
 * (`hiring`, `active`, `pending_verification`) — yang dibaca mesin tetap satu
 * bahasa, terjemahannya urusan klien. `label()` ada supaya terjemahan itu
 * punya satu sumber kalau nanti dibutuhkan di sisi server (mis. surel).
 *
 * Kolomnya NULLABLE dan sengaja begitu: pendaftaran tidak menanyakannya, dan
 * seluruh baris yang sudah ada lahir tanpa nilai ini. Kolom NOT NULL akan
 * memaksa menebak salah satu dari dua nilai untuk orang yang belum pernah
 * ditanya.
 */
enum Gender: string
{
    case Male = 'male';
    case Female = 'female';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Laki-laki',
            self::Female => 'Perempuan',
        };
    }
}
