<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class InvalidVerificationCodeException extends DomainException
{
    private function __construct(string $message, private readonly ?int $attemptsLeft = null)
    {
        parent::__construct($message);
    }

    public static function wrong(int $attemptsLeft): self
    {
        return new self('Kode verifikasi salah.', $attemptsLeft);
    }

    public static function expiredOrUsed(): self
    {
        return new self('Kode verifikasi sudah kedaluwarsa atau terpakai. Minta kode baru.');
    }

    public static function attemptsExhausted(): self
    {
        return new self('Percobaan kode habis. Minta kode baru.', 0);
    }

    public function errorCode(): string
    {
        return 'invalid_verification_code';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return $this->attemptsLeft === null ? [] : ['attempts_left' => $this->attemptsLeft];
    }
}
