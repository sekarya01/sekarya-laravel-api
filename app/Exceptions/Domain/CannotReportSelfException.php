<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class CannotReportSelfException extends DomainException
{
    public static function make(): self
    {
        return new self('Tidak bisa melaporkan diri sendiri.');
    }

    public function errorCode(): string
    {
        return 'cannot_report_self';
    }
}
