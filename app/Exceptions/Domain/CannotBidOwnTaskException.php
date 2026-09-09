<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class CannotBidOwnTaskException extends DomainException
{
    public static function make(): self
    {
        return new self('Tidak bisa mengajukan penawaran pada task sendiri.');
    }

    public function errorCode(): string
    {
        return 'cannot_bid_own_task';
    }

    public function httpStatus(): int
    {
        return 403;
    }
}
