<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** Kendala hanya bisa diajukan peserta task yang sedang `disputed` (G5). */
final class DisputeNotAllowedException extends DomainException
{
    private function __construct(string $message, private readonly string $reason)
    {
        parent::__construct($message);
    }

    public static function becauseStatus(string $status): self
    {
        return new self(
            "Kendala hanya bisa diajukan pada tugas berstatus `disputed` (sekarang: {$status}).",
            'wrong_status',
        );
    }

    public static function notParticipant(): self
    {
        return new self('Hanya peserta tugas ini yang bisa mengajukan kendala.', 'not_participant');
    }

    public function errorCode(): string
    {
        return 'dispute_not_allowed';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['reason' => $this->reason];
    }
}
