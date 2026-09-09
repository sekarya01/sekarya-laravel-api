<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityStatus: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Open => [self::InProgress],
            self::InProgress => [self::Submitted],
            self::Submitted => [self::Approved, self::Rejected],
            self::Rejected => [self::Submitted],
            self::Approved => [],
        }, true);
    }
}
