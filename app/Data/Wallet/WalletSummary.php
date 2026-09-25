<?php

declare(strict_types=1);

namespace App\Data\Wallet;

use Carbon\CarbonImmutable;

/** Hasil ringkasan saldo — angka yang sudah dijumlahkan basis data. */
final readonly class WalletSummary
{
    /**
     * @param  array<string, int>  $byType  setiap jenis mutasi ada, nol bila tidak ada baris
     */
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public int $totalIn,
        public int $totalOut,
        public int $count,
        public array $byType,
        public CarbonImmutable $weekStart,
        public int $earningsThisWeek,
        public int $earningsLastWeek,
    ) {}
}
