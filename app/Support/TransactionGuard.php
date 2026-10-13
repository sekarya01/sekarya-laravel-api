<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Penjaga runtime untuk tag `[#tx: caller]` — kelas yang sengaja tidak membuka
 * transaksi sendiri (savepoint bersarang = commit palsu) tapi menulis uang.
 * Dipanggil di luar transaksi, tulisan pertamanya ter-commit sendiri dan
 * kegagalan sesudahnya meninggalkan uang bergerak setengah jalan, tanpa galat.
 */
final class TransactionGuard
{
    /** @throws LogicException kalau tidak ada transaksi terbuka */
    public static function assertActive(string $caller): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException($caller.' wajib dipanggil di dalam transaksi basis data.');
        }
    }
}
