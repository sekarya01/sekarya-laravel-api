<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class EmailNotRegisteredException extends DomainException
{
    /**
     * Sengaja eksplisit: peminta reset HARUS tahu emailnya tidak terdaftar.
     * Berbeda dengan login/resend-code yang menyamarkan keberadaan akun,
     * di sini keberadaan akun adalah prasyarat alur yang diminta eksplisit.
     * Konsekuensinya endpoint ini bisa dipakai memeriksa email terdaftar —
     * itu trade-off yang disadari, diredam oleh throttle per-email.
     */
    public static function make(): self
    {
        return new self('Email tidak terdaftar. Periksa kembali atau daftar akun baru.');
    }

    public function errorCode(): string
    {
        return 'email_not_registered';
    }
}
