<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Akun super_admin adalah satu-satunya yang tidak bisa dihapus maupun
 * dinonaktifkan.
 *
 * Bukan kenyamanan: super_admin satu-satunya yang bisa membuat pengelola
 * baru. Menghapus atau menonaktifkannya berarti tidak ada lagi yang bisa
 * memulihkan akses pengelola dari dalam aplikasi — pemulihannya harus lewat
 * basis data, di lingkungan yang biasanya tidak punya SSH.
 */
final class SuperAdminIsProtectedException extends DomainException
{
    public static function cannotBeDeleted(): self
    {
        return new self('Akun super_admin tidak bisa dihapus.');
    }

    public static function cannotBeDemoted(): self
    {
        return new self('Peran akun super_admin tidak bisa diubah.');
    }

    public function errorCode(): string
    {
        return 'super_admin_protected';
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
