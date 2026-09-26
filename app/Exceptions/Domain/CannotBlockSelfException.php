<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

final class CannotBlockSelfException extends DomainException
{
    public static function make(): self
    {
        return new self('Tidak bisa memblokir diri sendiri.');
    }

    public function errorCode(): string
    {
        return 'cannot_block_self';
    }
}
