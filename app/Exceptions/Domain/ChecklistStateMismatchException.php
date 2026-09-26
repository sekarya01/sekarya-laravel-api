<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Centang checklist tidak sejajar dengan daftar langkah task (B10).
 *
 * Larik boolean yang panjangnya berbeda berarti pekerja mencentang langkah
 * yang tidak ada — menyimpannya akan menghasilkan checklist yang tidak bisa
 * dibaca kembali.
 */
final class ChecklistStateMismatchException extends DomainException
{
    public static function forCount(int $expected, int $given): self
    {
        return new self(sprintf(
            'Checklist punya %d langkah, tetapi %d centang dikirim.',
            $expected,
            $given,
        ));
    }

    public function errorCode(): string
    {
        return 'checklist_state_mismatch';
    }
}
