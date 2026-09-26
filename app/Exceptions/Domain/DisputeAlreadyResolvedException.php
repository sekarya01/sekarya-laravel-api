<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** Kendala sudah diputuskan (G5) — jawaban idempoten, bukan galat ganda. */
final class DisputeAlreadyResolvedException extends DomainException
{
    public static function make(): self
    {
        return new self('Kendala ini sudah diputuskan.');
    }

    public function errorCode(): string
    {
        return 'dispute_already_resolved';
    }
}
