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

    /**
     * Bentuk kanonik Indonesia `+62…` untuk DICARI, bukan untuk disimpan.
     *
     * `0812…`, `62812…`, `+62 812…`, dan `812…` semuanya menunjuk nomor yang
     * sama; yang disimpan di `users.phone` boleh salah satu ejaan (lihat
     * `PATCH /me`), jadi lookup login memakai kandidat — bukan mengubah
     * kolomnya.
     */
    public static function canonical(?string $raw): ?string
    {
        $digits = (string) preg_replace('/\D+/', '', (string) $raw);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0')) {
            return '+62'.substr($digits, 1);
        }

        return '+62'.$digits;
    }

    /**
     * Ejaan-ejaan yang mungkin tersimpan untuk satu nomor: bentuk kanonik dan
     * bentuk lokal `0…`. Dipakai `WHERE phone IN (…)` agar pengguna menemukan
     * akunnya apa pun ejaan yang tersimpan saat mendaftar.
     *
     * @return list<string>
     */
    public static function candidates(?string $raw): array
    {
        $canonical = self::canonical($raw);

        if ($canonical === null) {
            return [];
        }

        $local = '0'.substr($canonical, 3);

        return array_values(array_unique([$canonical, $local]));
    }
}
