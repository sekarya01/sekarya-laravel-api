<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kosakata tindakan pengelola — dipakai `admin_audit_logs.action`.
 *
 * Enum, bukan string bebas: jejak audit hanya berguna kalau bisa dikelompokkan
 * dan disaring, dan string bebas selalu berakhir dengan dua ejaan untuk satu
 * tindakan yang sama. Nilainya juga bagian dari kontrak — begitu tercatat di
 * basis data, ia tidak bisa diganti nama tanpa membuat jejak lama tak terbaca.
 */
enum AdminAction: string
{
    /**
     * Membuka detail pengajuan — satu-satunya tindakan BACA yang dicatat.
     *
     * Dicatat karena detail itulah satu-satunya tempat NIK dan nomor
     * rekening keluar dari basis data dalam bentuk terbaca. Keputusan bisa
     * ditinjau dari statusnya; pembacaan tidak meninggalkan bekas apa pun
     * kalau tidak dicatat di sini, dan "siapa pernah melihat NIK siapa"
     * adalah pertanyaan yang akan ditanyakan suatu hari.
     */
    case VerificationViewed = 'verification.viewed';

    case VerificationApproved = 'verification.approved';
    case VerificationRejected = 'verification.rejected';
    case VerificationRevoked = 'verification.revoked';

    case PaymentConfirmed = 'payment.confirmed';
    case PaymentRejected = 'payment.rejected';

    case UserSuspended = 'user.suspended';
    case UserBanned = 'user.banned';
    case UserReinstated = 'user.reinstated';

    case AdminCreated = 'admin.created';
    case AdminDeleted = 'admin.deleted';

    /**
     * Slug tabel yang disentuh, bukan nama kelas PHP.
     *
     * Diturunkan dari tindakannya, tidak diminta dari pemanggil: kalau
     * pemanggil yang menentukan, cepat atau lambat ada dua slug untuk satu
     * tabel dan penyaringan jejak berhenti bekerja.
     */
    public function subjectType(): string
    {
        return match ($this) {
            self::VerificationViewed,
            self::VerificationApproved,
            self::VerificationRejected,
            self::VerificationRevoked => 'user_verification',

            self::PaymentConfirmed,
            self::PaymentRejected => 'payment',

            self::UserSuspended,
            self::UserBanned,
            self::UserReinstated => 'user',

            self::AdminCreated,
            self::AdminDeleted => 'admin',
        };
    }

    /** Hanya super_admin. Lihat AdminRole::can(). */
    public function isAdminManagement(): bool
    {
        return match ($this) {
            self::AdminCreated, self::AdminDeleted => true,
            default => false,
        };
    }

    /**
     * Tindakan yang merugikan orang harus menyebut alasannya.
     *
     * Ditegakkan FormRequest di sisi HTTP dan Action di sisi domain — jejak
     * penolakan tanpa alasan tidak bisa ditinjau ulang oleh siapa pun,
     * termasuk oleh pengelola yang menuliskannya.
     */
    public function requiresReason(): bool
    {
        return match ($this) {
            self::VerificationRejected,
            self::VerificationRevoked,
            self::PaymentRejected,
            self::UserSuspended,
            self::UserBanned => true,
            default => false,
        };
    }
}
