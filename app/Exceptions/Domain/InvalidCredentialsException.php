<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class InvalidCredentialsException extends DomainException
{
    /**
     * Pesannya sengaja TIDAK membedakan "email tidak terdaftar" dari
     * "kata sandi salah". Membedakannya mengubah endpoint login jadi alat
     * pengecek keberadaan akun.
     */
    public static function make(): self
    {
        return new self('Email atau kata sandi salah.');
    }

    public function errorCode(): string
    {
        return 'invalid_credentials';
    }

    public function httpStatus(): int
    {
        return 401;
    }
}
