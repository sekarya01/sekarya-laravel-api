<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Enums\PaymentStatus;

/** Activity tidak boleh dibuka sebelum dana ditahan. */
final class PaymentNotHeldException extends DomainException
{
    private function __construct(string $message, private readonly PaymentStatus $status)
    {
        parent::__construct($message);
    }

    public static function becauseStatus(PaymentStatus $status): self
    {
        return new self(
            "Dana belum ditahan, activity tidak bisa dibuka (status: {$status->value}).",
            $status,
        );
    }

    public function errorCode(): string
    {
        return 'payment_not_held';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['payment_status' => $this->status->value];
    }
}
