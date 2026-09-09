<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * STUB — mekanisme pembayaran belum diriset.
 * Satu aturan yang berlaku sekarang: Held adalah gerbang pembuka Activity.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Held = 'held';
    case Released = 'released';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    /** Dana sudah masuk dan ditahan → activity boleh dibuka. */
    public function opensActivity(): bool
    {
        return $this === self::Held;
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Pending => [self::Held, self::Cancelled],
            self::Held => [self::Released, self::Refunded],
            self::Released, self::Refunded, self::Cancelled => [],
        }, true);
    }
}
