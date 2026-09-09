<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\UserStatus;

final class AccountNotActiveException extends DomainException
{
    private function __construct(string $message, private readonly UserStatus $status)
    {
        parent::__construct($message);
    }

    public static function becauseStatus(UserStatus $status): self
    {
        return new self("Akun tidak aktif (status: {$status->value}).", $status);
    }

    public function errorCode(): string
    {
        return 'account_not_active';
    }

    public function httpStatus(): int
    {
        return 403;
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['status' => $this->status->value];
    }
}
