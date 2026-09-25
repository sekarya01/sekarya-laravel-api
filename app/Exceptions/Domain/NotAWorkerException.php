<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Aksi khusus pekerja dari akun yang belum punya profil pekerja.
 *
 * Dipakai `PUT me/worker {is_available}`: sakelar "Siap menerima kerja" tidak
 * boleh menjadi pintu belakang pembuatan profil pekerja.
 */
final class NotAWorkerException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Hanya pekerja yang bisa mengatur ketersediaan. Buka profil pekerja lebih dulu.');
    }

    public function errorCode(): string
    {
        return 'not_a_worker';
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
