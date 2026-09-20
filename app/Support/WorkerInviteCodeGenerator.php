<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Generator kode 8 char: huruf (a-z, A-Z tanpa O/l/I agar tak tertukar),
 * angka (2-9 tanpa 0/1), dan special char dari himpunan aman URL.
 *
 * Dijamin tiap kode memuat MINIMAL satu dari tiap kelompok — kalau acak murni,
 * sebagian kode lolos tanpa special char dan syarat "gabungan" jadi dusta.
 */
final class WorkerInviteCodeGenerator
{
    public const LENGTH = 8;

    private const LETTERS = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ';
    private const DIGITS = '23456789';
    private const SPECIAL = '!@#$%-*+=';

    public static function generate(): string
    {
        $pick = fn (string $pool): string => $pool[random_int(0, strlen($pool) - 1)];

        $chars = [$pick(self::LETTERS), $pick(self::DIGITS), $pick(self::SPECIAL)];
        $all = self::LETTERS.self::DIGITS.self::SPECIAL;
        for ($i = 3; $i < self::LENGTH; $i++) {
            $chars[] = $pick($all);
        }
        // Fisher–Yates agar posisi wajib tidak selalu di depan.
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    /** Validasi bentuk sebelum di-hash: 8 char, tiap kelompok minimal satu. */
    public static function isWellFormed(string $code): bool
    {
        if (mb_strlen($code) !== self::LENGTH) {
            return false;
        }
        return (bool) preg_match('/[A-Za-z]/', $code)
            && (bool) preg_match('/[0-9]/', $code)
            && (bool) preg_match('/[!@#$%\-\*\+=]/', $code);
    }
}
