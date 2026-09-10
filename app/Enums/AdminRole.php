<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kewenangan pengelola. SSOT-nya `can()` — Policy dan test membacanya dari
 * sini, tidak menuliskan aturannya sendiri.
 *
 * Dua peran, dan pemisahnya cuma satu hal: mengelola akun pengelola lain.
 * Pekerjaan sehari-hari (verifikasi, pembayaran, moderasi pengguna) memang
 * pekerjaan `admin` — itu sebabnya peran itu ada, supaya orang yang menilai
 * KTP tidak perlu memegang kunci yang bisa membuat pengelola baru.
 */
enum AdminRole: string
{
    /** Satu-satunya, tidak bisa dihapus, boleh membuat dan menghapus admin. */
    case SuperAdmin = 'super_admin';

    /** Dibuat oleh super_admin, bisa dihapus. Tidak bisa mengelola pengelola. */
    case Admin = 'admin';

    public function can(AdminAction $action): bool
    {
        return ! $action->isAdminManagement() || $this->canManageAdmins();
    }

    /**
     * Boleh membuat dan menghapus akun pengelola?
     *
     * SSOT-nya di sini, bukan di dua tempat: gerbang rute (middleware
     * EnsureAdminManagesAdmins) dan pemeriksaan di dalam Action sama-sama
     * membacanya lewat method ini. Ditulis dua kali, penambahan peran ketiga
     * akan memperbarui satu dan meninggalkan yang lain — dan yang tertinggal
     * adalah yang memutuskan akses.
     */
    public function canManageAdmins(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function isSuperAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Admin => 'Admin',
        };
    }
}
