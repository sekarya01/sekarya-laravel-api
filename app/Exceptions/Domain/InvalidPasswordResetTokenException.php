<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class InvalidPasswordResetTokenException extends DomainException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * Satu pesan untuk dua kondisi kedaluwarsa: waktu habis (60 menit,
     * standar broker Laravel) maupun sudah terpakai (sekali pakai —
     * token dihapus saat reset berhasil). Membedakannya tidak membantu
     * pemilik sah, tapi membantu penyerang.
     */
    public static function expiredOrUsed(): self
    {
        return new self('Tautan reset sudah kedaluwarsa atau terpakai. Minta tautan baru.');
    }

    public function errorCode(): string
    {
        return 'invalid_reset_token';
    }
}
