<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class NotTaskParticipantException extends DomainException
{
    /** 404, bukan 403 — 403 mengonfirmasi bahwa task itu ada. */
    public static function make(): self
    {
        return new self('Task tidak ditemukan.');
    }

    public function errorCode(): string
    {
        return 'task_not_found';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
