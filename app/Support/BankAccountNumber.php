<?php

declare(strict_types=1);

namespace App\Support;

/**
 * SATU aturan "empat digit terakhir" dan bentuk maskernya.
 *
 * Dipakai saat pengajuan rekening (menulis kolom), oleh penyusulan baris lama,
 * dan oleh resource (membaca). Kalau penulis dan pembaca memakai aturan yang
 * berbeda — mis. satu membuang tanda hubung, yang lain tidak — masker yang
 * tampil bukan milik rekening orangnya, tanpa galat apa pun.
 */
final class BankAccountNumber
{
    /** Empat digit terakhir; tanda baca/spasi di nomor diabaikan. `null` bila tak ada digit. */
    public static function lastFour(?string $number): ?string
    {
        $digits = (string) preg_replace('/\D/', '', (string) $number);

        return $digits === '' ? null : substr($digits, -4);
    }

    /** "•••• 4910". Nomor utuh tidak pernah melewati fungsi ini. */
    public static function mask(?string $lastFour): ?string
    {
        return $lastFour === null || $lastFour === '' ? null : '•••• '.$lastFour;
    }
}
