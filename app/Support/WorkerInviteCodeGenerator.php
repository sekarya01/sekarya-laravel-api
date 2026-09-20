<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Generator kode 8 char string: huruf kecil + huruf KAPITAL + angka +
 * special char, acak per kode.
 *
 * Tiap kode dijamin memuat MINIMAL satu dari tiap kelompok — kalau acak
 * murni, sebagian kode lolos tanpa special char (atau tanpa kapital) dan
 * syarat "gabungan" jadi dusta. Huruf yang mudah tertukar dibuang
 * (l, o, O, I, 0, 1) supaya kode yang dibacakan lewat telepon tidak salah
 * ketik.
 */
final class WorkerInviteCodeGenerator
{
    public const LENGTH = 8;

    private const LOWER = 'abcdefghjkmnpqrstuvwxyz';
    private const UPPER = 'ABCDEFGHJKMNPQRSTUVWXYZ';
    private const DIGITS = '23456789';
    private const SPECIAL = '!@#$%-*+=';

    public static function generate(): string
    {
        $pick = fn (string $pool): string => $pool[random_int(0, strlen($pool) - 1)];

        // Satu wajib dari tiap kelompok, sisanya acak dari gabungan.
        $chars = [$pick(self::LOWER), $pick(self::UPPER), $pick(self::DIGITS), $pick(self::SPECIAL)];
        $all = self::LOWER.self::UPPER.self::DIGITS.self::SPECIAL;
        for ($i = 4; $i < self::LENGTH; $i++) {
            $chars[] = $pick($all);
        }
        // Fisher–Yates agar posisi wajib tidak selalu di depan.
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    /** Validasi bentuk: 8 char, tiap kelompok (kecil, KAPITAL, angka, spesial) minimal satu. */
    public static function isWellFormed(string $code): bool
    {
        if (mb_strlen($code) !== self::LENGTH) {
            return false;
        }
        return (bool) preg_match('/[a-z]/', $code)
            && (bool) preg_match('/[A-Z]/', $code)
            && (bool) preg_match('/[0-9]/', $code)
            && (bool) preg_match('/[!@#$%\-\*\+=]/', $code);
    }
}
