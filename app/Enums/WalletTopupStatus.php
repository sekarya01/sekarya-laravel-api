<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Isi saldo mengikuti pola yang sama dengan pembayaran task: pengguna
 * MELAPOR sudah transfer, pengelola yang melihat mutasi rekening yang
 * menyatakannya masuk.
 *
 * Karena itu tidak ada status `pending`. Baris ini lahir dari permintaan
 * pengguna, dan permintaan itu ADALAH laporannya — berbeda dari `payments`,
 * yang barisnya sudah ada sejak pelamar pertama diterima, jauh sebelum ada
 * transfer yang dilaporkan.
 */
enum WalletTopupStatus: string
{
    /** Menunggu pengelola. Inilah antrean /admin/wallet/topups. */
    case AwaitingConfirmation = 'awaiting_confirmation';

    /** Dana terlihat di mutasi → saldo sudah bertambah. */
    case Confirmed = 'confirmed';

    /** Dana tidak ditemukan. Pengguna mengajukan baru, bukan memperbaiki ini. */
    case Rejected = 'rejected';

    /** Dibatalkan pengguna sendiri sebelum diputuskan. */
    case Cancelled = 'cancelled';

    public function awaitsConfirmation(): bool
    {
        return $this === self::AwaitingConfirmation;
    }

    public function isFinal(): bool
    {
        return $this !== self::AwaitingConfirmation;
    }

    /**
     * Tidak ada jalan kembali dari status final.
     *
     * Berbeda dari `payments`, yang penolakannya mengembalikan tagihan ke
     * `pending` supaya bisa dilaporkan ulang. Di sana barisnya melekat pada
     * satu task dan tidak boleh berlipat; di sini pengajuan ulang cukup
     * membuat baris baru, dan riwayat penolakan tetap utuh sebagai sinyal.
     */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::AwaitingConfirmation => [self::Confirmed, self::Rejected, self::Cancelled],
            self::Confirmed, self::Rejected, self::Cancelled => [],
        }, true);
    }
}
