<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Kata sandi saat ini salah pada `POST auth/change-password` (G4).
 *
 * Dipisah dari `invalid_credentials` supaya pengguna yang sudah login
 * mendapat pesan yang jelas tanpa membocorkan apa pun: ia memang pemilik akun
 * ini, hanya salah mengetik sandi lamanya.
 */
final class InvalidCurrentPasswordException extends DomainException
{
    public static function make(): self
    {
        return new self('Kata sandi saat ini salah.');
    }

    public function errorCode(): string
    {
        return 'invalid_current_password';
    }
}
