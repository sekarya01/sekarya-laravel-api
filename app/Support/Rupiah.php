<?php

declare(strict_types=1);

namespace App\Support;

/**
 * SATU format rupiah untuk teks yang dibaca manusia (push, pesan galat, jejak
 * audit, dasbor): tanpa desimal, titik pemisah ribuan — "Rp1.250.000".
 * API sendiri tetap mengirim angka bulat; ini hanya untuk tampilan.
 */
final class Rupiah
{
    public static function format(int|float|string|null $amount): string
    {
        return 'Rp'.number_format((float) $amount, 0, ',', '.');
    }
}
