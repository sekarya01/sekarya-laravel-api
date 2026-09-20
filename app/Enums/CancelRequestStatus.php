<?php

declare(strict_types=1);

namespace App\Enums;

enum CancelRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
