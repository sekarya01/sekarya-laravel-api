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

    /** Yang masih menunggu keputusan pengelola. Inilah antrean /admin. */
    public function awaitsReview(): bool
    {
        return ! $this->isFinal();
    }

    /**
     * Perpindahan status yang boleh dilakukan pengelola.
     *
     * `isFinal()` menjawab pertanyaan lain — "apakah pengajuan ini sudah
     * selesai dinilai" — dan sengaja tidak dipakai di sini, karena satu
     * status final MASIH punya lanjutan: verifikasi yang sudah `verified`
     * harus bisa dicabut. Badge "terverifikasi" dihitung dari status ini,
     * jadi tanpa jalur pencabutan, identitas yang ternyata palsu tidak bisa
     * ditarik kembali.
     *
     * Yang TIDAK ada di sini: jalan kembali dari `rejected`. Pengajuan yang
     * ditolak diperbaiki dengan mengajukan ulang (SubmitVerificationAction
     * membuat baris baru dan meninggalkan yang lama sebagai riwayat), bukan
     * dengan pengelola mengubah keputusannya di baris yang sama — riwayat
     * penolakan itu sinyal yang tidak boleh bisa dihapus dari dalam.
     */
    public function canTransitionTo(self $next): bool
    {
        return in_array($next, match ($this) {
            self::Pending, self::InReview => [self::InReview, self::Verified, self::Rejected],
            self::Verified => [self::Revoked],
            self::Rejected, self::Revoked => [],
        }, true);
    }
}
