<?php

declare(strict_types=1);

namespace App\Enums;

enum VerificationType: string
{
    /** Foto KTP + selfie pegang KTP, dinilai bersamaan. */
    case Identity = 'identity';
    case BankAccount = 'bank_account';

    public function requiresPhotos(): bool
    {
        return $this === self::Identity;
    }
}
