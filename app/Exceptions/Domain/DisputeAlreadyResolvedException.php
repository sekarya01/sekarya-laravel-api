<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/** Sengketa sudah diputuskan — jawaban idempoten, bukan galat ganda. */
final class DisputeAlreadyResolvedException extends DomainException
{
    public static function make(): self
    {
        return new self('Sengketa ini sudah diputuskan.');
    }

    public function errorCode(): string
    {
        return 'dispute_already_resolved';
    }
}
