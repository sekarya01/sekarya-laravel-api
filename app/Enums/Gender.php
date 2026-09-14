<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Jenis kelamin: dua nilai yang tercetak di KTP, plus satu penolakan eksplisit.
 *
 * Nilainya bahasa Inggris seperti seluruh enum lain di aplikasi ini
 * (`hiring`, `active`, `pending_verification`) — yang dibaca mesin tetap satu
 * bahasa, terjemahannya urusan klien. `label()` ada supaya terjemahan itu
 * punya satu sumber kalau nanti dibutuhkan di sisi server (mis. surel).
 *
 * Kolomnya NULLABLE dan sengaja begitu. `null` dan [self::PreferNotToSay]
 * BUKAN hal yang sama: `null` berarti belum pernah ditanya (baris lama, atau
 * pendaftar yang melewatkan field opsional ini), sedangkan `prefer_not_to_say`
 * berarti sudah ditanya dan memilih tidak menjawab.
 *
 * Bedanya terasa di kelengkapan identitas: `prefer_not_to_say` sudah dianggap
 * terisi, jadi pemiliknya bisa lanjut jadi pekerja. Yang `null` belum.
 */
enum Gender: string
{
    case Male = 'male';
    case Female = 'female';
    case PreferNotToSay = 'prefer_not_to_say';

    public function label(): string
    {
        return match ($this) {
            self::Male => 'Laki-laki',
            self::Female => 'Perempuan',
            self::PreferNotToSay => 'Tidak ingin menyebutkan',
        };
    }
}
