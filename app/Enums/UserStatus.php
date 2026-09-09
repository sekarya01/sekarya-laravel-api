<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    /** Sudah mendaftar, email BELUM diverifikasi. Tidak bisa apa-apa. */
    case PendingVerification = 'pending_verification';

    case Active = 'active';
    case Suspended = 'suspended';
    case Banned = 'banned';

    /** Boleh memakai aplikasi. Hanya Active. */
    public function canTransact(): bool
    {
        return $this === self::Active;
    }

    /** Boleh diberi token akses. */
    public function canReceiveTokens(): bool
    {
        return $this === self::Active;
    }

    public function isPendingVerification(): bool
    {
        return $this === self::PendingVerification;
    }
}
