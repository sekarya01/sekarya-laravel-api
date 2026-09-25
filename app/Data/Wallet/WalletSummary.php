<?php

declare(strict_types=1);

namespace App\Data\Wallet;

use Carbon\CarbonImmutable;

/**
 * Hasil ringkasan saldo (B2) — angka yang sudah dijumlahkan basis data.
 *
 * Nama field mengikuti kontrak dokumen redesign: `credit_total`,
 * `debit_total`, `entries_count`, `earning_total`. `previous*` hanya terisi
 * bila `compare_previous=1`; `byMonth` hanya bila `group=month`.
 */
final readonly class WalletSummary
{
    /**
     * @param  array<string, int>  $byType  setiap jenis mutasi ada, nol bila tidak ada baris
     * @param  list<array{month: string, credit_total: int, debit_total: int, entries_count: int, earning_total: int}>  $byMonth
     */
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public int $creditTotal,
        public int $debitTotal,
        public int $entriesCount,
        public array $byType,
        public int $earningTotal,
        public ?int $previousCreditTotal = null,
        public ?int $previousEarningTotal = null,
        public array $byMonth = [],
    ) {}
}
