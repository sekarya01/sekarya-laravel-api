<?php

declare(strict_types=1);

namespace App\Support;

/**
 * SATU aturan bentuk nomor HP, dipakai pendaftaran DAN sunting profil.
 *
 * Kalau dua jalur tulis memakai aturan berbeda, nomor yang ditolak saat
 * mendaftar bisa masuk lewat `PATCH /me` — dan kolom `users.phone` yang unik
 * mulai berisi dua ejaan untuk satu nomor tanpa galat apa pun.
 */
final class PhoneNumber
{
    /** Angka, boleh diawali `+`, 9-19 digit. */
    public const string PATTERN = '/^\+?[0-9]{9,19}$/';

    /** Spasi dibuang; `''` berarti tidak ada nomor. */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $phone = (string) preg_replace('/\s+/', '', $raw);

        return $phone !== '' ? $phone : null;
    }
}
