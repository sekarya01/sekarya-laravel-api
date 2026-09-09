<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * budget_min adalah batas keras. budget_max TIDAK divalidasi —
 * poster diberi kebebasan penuh memilih, termasuk tawaran di atas anggaran.
 */
final class BidBelowMinimumException extends DomainException
{
    private function __construct(string $message, private readonly int $minimum)
    {
        parent::__construct($message);
    }

    public static function forTask(int $budgetMin): self
    {
        return new self('Penawaran tidak boleh di bawah budget minimum task.', $budgetMin);
    }

    public function errorCode(): string
    {
        return 'bid_below_minimum';
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['budget_min' => $this->minimum];
    }
}
