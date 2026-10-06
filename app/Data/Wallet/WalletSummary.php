<?php

declare(strict_types=1);

namespace App\Data\Wallet;

use Carbon\CarbonImmutable;

/**
 * Hasil ringkasan saldo (B2) — angka yang sudah dijumlahkan basis data.
 *
 * Nama field mengikuti kontrak dokumen redesign: `credit_total`,
 * `debit_total`, `entries_count`, `earning_total`, `earning_count`.
 * `previous*` hanya terisi bila `compare_previous=1`; `byMonth` hanya bila
 * `group=month`; `byDay`/`byWeek` (`null` = tidak diminta) bila
 * `group=day|week`; `byCategory` (`null` = tidak diminta) bila
 * `with_categories=1`.
 */
final readonly class WalletSummary
{
    /**
     * @param  array<string, int>  $byType  setiap jenis mutasi ada, nol bila tidak ada baris
     * @param  list<array<string, int|string>>  $byMonth  `{month, credit_total, debit_total, entries_count, earning_total, earning_count}`
     * @param  list<array<string, int|string>>|null  $byDay  `{date, …}` — terisi nol, urut naik
     * @param  list<array<string, int|string>>|null  $byWeek  `{week_start, …}` — pekan mulai Senin, terisi nol
     * @param  list<array{slug: string, name: string, icon: string|null, earning_total: int, earning_count: int}>|null  $byCategory
     */
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public int $creditTotal,
        public int $debitTotal,
        public int $entriesCount,
        public array $byType,
        public int $earningTotal,
        public int $earningCount = 0,
        public ?int $previousCreditTotal = null,
        public ?int $previousEarningTotal = null,
        public ?int $previousEarningCount = null,
        public array $byMonth = [],
        public ?array $byDay = null,
        public ?array $byWeek = null,
        public ?array $byCategory = null,
    ) {}
}
