<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class ResendTooSoonException extends DomainException
{
    private function __construct(string $message, private readonly int $retryAfterSeconds)
    {
        parent::__construct($message);
    }

    public static function retryAfter(int $seconds): self
    {
        return new self("Tunggu {$seconds} detik sebelum meminta kode lagi.", $seconds);
    }

    public function errorCode(): string
    {
        return 'resend_too_soon';
    }

    public function httpStatus(): int
    {
        return 429;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['retry_after_seconds' => $this->retryAfterSeconds];
    }
}
