<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

/**
 * Saldo tidak cukup untuk pemotongan yang diminta.
 *
 * Dilempar `App\Support\WalletLedger`, bukan oleh Action yang memanggilnya.
 * Pemeriksaan "cukup atau tidak" harus terjadi di dalam kunci baris yang sama
 * dengan penulisannya — dilakukan lebih dulu oleh pemanggil, ia cuma
 * pemeriksaan pada angka yang sudah basi begitu dua permintaan datang
 * bersamaan, dan dua penarikan bisa lolos atas saldo yang sama.
 */
final class InsufficientBalanceException extends DomainException
{
    private function __construct(
        private readonly int $balance,
        private readonly int $requested,
    ) {
        parent::__construct(sprintf(
            'Saldo tidak cukup. Tersedia Rp%s, diminta Rp%s.',
            number_format((float) $balance, 0, ',', '.'),
            number_format((float) $requested, 0, ',', '.'),
        ));
    }

    public static function forAmount(int $balance, int $requested): self
    {
        return new self($balance, $requested);
    }

    public function errorCode(): string
    {
        return 'insufficient_balance';
    }

    /**
     * Angkanya disebut supaya klien bisa menampilkan kekurangannya tanpa
     * memanggil ulang endpoint saldo — dan tanpa mengurai `message`.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return [
            'balance' => $this->balance,
            'requested' => $this->requested,
            'shortfall' => $this->requested - $this->balance,
        ];
    }
}
