<?php

declare(strict_types=1);

namespace App\Enums;

enum VerificationStatus: string
{
    case Pending = 'pending';
    case InReview = 'in_review';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case Revoked = 'revoked';

    /** Hanya ini yang boleh memunculkan badge "terverifikasi". */
    public function isVerified(): bool
    {
        return $this === self::Verified;
    }

    public function isFinal(): bool
    {
        return match ($this) {
            self::Verified, self::Rejected, self::Revoked => true,
            self::Pending, self::InReview => false,
        };
    }
}
