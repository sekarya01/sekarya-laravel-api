<?php

declare(strict_types=1);

namespace App\Data\Wallet;

/**
 * Satu rekening tujuan isi saldo (transfer manual) — B1.
 *
 * Nilainya datang dari konfigurasi lingkungan, bukan basis data: rekening
 * tujuan adalah keputusan operasional, bukan data pengguna.
 */
final readonly class TopupAccount
{
    public function __construct(
        public ?string $bankCode,
        public ?string $bankName,
        public ?string $accountNumber,
        public ?string $accountHolder,
    ) {}
}
