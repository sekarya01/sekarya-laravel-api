<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class EmailNotVerifiedException extends DomainException
{
    public static function make(): self
    {
        return new self('Email belum diverifikasi. Masukkan kode yang dikirim ke email Anda.');
    }

    public function errorCode(): string
    {
        return 'email_not_verified';
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
