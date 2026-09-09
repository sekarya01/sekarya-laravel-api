<?php

declare(strict_types=1);

namespace App\Enums;

enum BidStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';

    public function isOpen(): bool
    {
        return $this === self::Pending;
    }
}
