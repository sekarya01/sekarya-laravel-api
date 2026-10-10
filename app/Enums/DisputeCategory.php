<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kategori alasan sengketa — dipilih pemberi kerja, dibaca pengelola.
 *
 * Kategori, bukan teks bebas saja: pengelola menimbang "terlambat" dengan
 * bukti yang berbeda dari "tidak sesuai" (jam tiba & selesai di activity vs
 * foto hasil), dan antrean bisa disaring. Deskripsi tetap wajib.
 */
enum DisputeCategory: string
{
    case NotAsAgreed = 'not_as_agreed';
    case Late = 'late';
    case Incomplete = 'incomplete';
    case Damage = 'damage';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::NotAsAgreed => 'Hasil tidak sesuai',
            self::Late => 'Terlambat dari jadwal',
            self::Incomplete => 'Pekerjaan tidak selesai',
            self::Damage => 'Ada kerusakan/kehilangan',
            self::Other => 'Lainnya',
        };
    }
}
