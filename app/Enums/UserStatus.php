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

    /**
     * Perpindahan status yang boleh dilakukan PENGELOLA.
     *
     * Terpisah dari alur pengguna sendiri: verifikasi email yang memindahkan
     * `pending_verification` → `active` bukan tindakan pengelola, dan tidak
     * boleh bisa ditiru dari `/admin` — pengelola yang bisa mengaktifkan akun
     * tanpa kode berarti verifikasi email bisa dilewati dari dalam.
     *
     * Karena itu `reinstate` TIDAK selalu berujung `active`: akun yang belum
     * pernah memverifikasi email dikembalikan ke `pending_verification`, dan
     * harus menyelesaikan kodenya sendiri. Yang menentukan tujuan itu
     * ChangeUserStatusAction, yang membaca `email_verified_at`.
     */
    public function canBeMovedByAdminTo(self $next): bool
    {
        if ($next === $this) {
            return false;
        }

        return in_array($next, match ($this) {
            self::Active, self::PendingVerification => [self::Suspended, self::Banned],
            // Pemulihan bisa salah sasaran, jadi ban harus bisa dibatalkan.
            self::Suspended, self::Banned => [
                self::Active,
                self::PendingVerification,
                self::Suspended,
                self::Banned,
            ],
        }, true);
    }
}
