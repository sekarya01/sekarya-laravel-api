<?php

declare(strict_types=1);

namespace App\Support;

/**
 * SATU normalisasi alamat email untuk menulis DAN mencari (pendaftaran,
 * login, pemulihan sandi, pengelola, dasbor, CLI). Kalau dua jalur memakai
 * aturan berbeda, "Budi@x.test" yang terdaftar tidak pernah cocok dengan
 * "budi@x.test" yang dicari — tanpa galat, hanya "tidak ditemukan".
 */
final class Email
{
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
