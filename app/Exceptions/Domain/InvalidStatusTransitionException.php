<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class InvalidStatusTransitionException extends DomainException
{
    private function __construct(
        string $message,
        private readonly string $from,
        private readonly string $to,
    ) {
        parent::__construct($message);
    }

    public static function between(string $from, string $to): self
    {
        return new self("Tidak bisa berpindah dari {$from} ke {$to}.", $from, $to);
    }

    public function errorCode(): string
    {
        return 'invalid_status_transition';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['from' => $this->from, 'to' => $this->to];
    }
}
