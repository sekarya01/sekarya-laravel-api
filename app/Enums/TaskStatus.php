<?php

declare(strict_types=1);

namespace App\Enums;

enum TaskStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Dealt = 'dealt';
    case Active = 'active';
    case Submitted = 'submitted';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case Disputed = 'disputed';
    case Refunded = 'refunded';

    /** SSOT alur status task. */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /** @return list<self> */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Draft => [self::Open, self::Cancelled],
            self::Open => [self::Dealt, self::Expired, self::Cancelled],
            self::Dealt => [self::Active, self::Cancelled],
            self::Active => [self::Submitted, self::Cancelled],
            self::Submitted => [self::Completed, self::Disputed],
            self::Disputed => [self::Completed, self::Refunded],
            self::Completed, self::Expired, self::Cancelled, self::Refunded => [],
        };
    }

    /** Masih menerima penawaran. */
    public function acceptsBids(): bool
    {
        return $this === self::Open;
    }

    public function isFinal(): bool
    {
        return $this->allowedNext() === [];
    }
}
