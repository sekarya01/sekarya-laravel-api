<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Penarikan saldo ke rekening.
 *
 * SALDONYA SUDAH BERKURANG SEJAK `Requested`, bukan saat `Completed`. Kalau
 * pemotongan menunggu pencairan, saldo yang sama bisa diminta berkali-kali
 * selama antrean pengelola belum tersentuh — dan yang membayarnya bukan
 * penggunanya. Karena itu `Rejected` dan `Cancelled` MENGEMBALIKAN tahanan
 * itu (`WalletEntryType::WithdrawalReversal`), dan `Completed` tidak menyentuh
 * saldo sama sekali.
 */
enum WalletWithdrawalStatus: string
{
    /** Diminta pengguna; saldonya sudah ditahan. */
    case Requested = 'requested';

    /** Sudah ditransfer pengelola ke rekening tujuan. */
    case Completed = 'completed';

    /** Ditolak pengelola → tahanan dikembalikan. */
    case Rejected = 'rejected';

    /** Dibatalkan pengguna sendiri → tahanan dikembalikan. */
    case Cancelled = 'cancelled';

    /** Menunggu pengelola. Inilah antrean /admin/wallet/withdrawals. */
    public function awaitsProcessing(): bool
    {
        return $this === self::Requested;
    }

    public function isFinal(): bool
    {
        return $this !== self::Requested;
    }

    /** Status yang mengembalikan dana yang tadi ditahan. */
    public function returnsHeldFunds(): bool
    {
        return $this === self::Rejected || $this === self::Cancelled;
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Requested => [self::Completed, self::Rejected, self::Cancelled],
            self::Completed, self::Rejected, self::Cancelled => [],
        }, true);
    }
}
