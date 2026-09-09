<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class TaskAlreadyDealtException extends DomainException
{
    public static function make(): self
    {
        return new self('Task ini sudah deal dengan penerima kerja lain.');
    }

    public function errorCode(): string
    {
        return 'task_already_dealt';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
