<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * STUB — mekanisme pembayaran belum diriset.
 *
 * Dua aturan yang sudah berlaku:
 *
 *  1. `Held` adalah gerbang pembuka Activity.
 *  2. `Held` HANYA bisa dicapai dari `AwaitingConfirmation`, dan yang
 *     memindahkannya cuma pengelola. Pemberi kerja melapor sudah transfer;
 *     yang menyatakan dana benar-benar diterima adalah orang yang melihat
 *     mutasi rekening. Sebelum ini pemberi kerja mencapai `Held` sendiri,
 *     artinya ia menyatakan sendiri uangnya sudah masuk.
 */
enum PaymentStatus: string
{
    case Pending = 'pending';

    /** Pemberi kerja mengaku sudah transfer. Belum ada uang yang dianggap masuk. */
    case AwaitingConfirmation = 'awaiting_confirmation';

    case Held = 'held';
    case Released = 'released';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    /** Dana sudah masuk dan ditahan → activity boleh dibuka. */
    public function opensActivity(): bool
    {
        return $this === self::Held;
    }

    /** Menunggu keputusan pengelola. Inilah antrean di /admin/payments. */
    public function awaitsConfirmation(): bool
    {
        return $this === self::AwaitingConfirmation;
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Pending => [self::AwaitingConfirmation, self::Cancelled],
            // Ditolak pengelola → kembali ke Pending, boleh dilaporkan ulang.
            self::AwaitingConfirmation => [self::Held, self::Pending, self::Cancelled],
            self::Held => [self::Released, self::Refunded],
            self::Released, self::Refunded, self::Cancelled => [],
        }, true);
    }
}
