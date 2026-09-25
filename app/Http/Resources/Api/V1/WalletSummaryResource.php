<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Data\Wallet\WalletSummary;
use Illuminate\Http\Request;

/**
 * Ringkasan saldo sendiri. Seluruh angka bilangan bulat rupiah.
 *
 * `by_type` SELALU memuat setiap jenis mutasi (nol bila tidak ada), supaya
 * klien tidak perlu membedakan "kunci hilang" dari "nol" — dan supaya ia
 * serialisasi sebagai objek, tidak pernah `[]`.
 *
 * @property WalletSummary $resource
 */
final class WalletSummaryResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $summary = $this->resource;

        return [
            'from' => $this->iso($summary->from),
            'to' => $this->iso($summary->to),
            'total_in' => $summary->totalIn,
            'total_out' => $summary->totalOut,
            'count' => $summary->count,
            'by_type' => (object) $summary->byType,
            'week_start' => $this->iso($summary->weekStart),
            'earnings_this_week' => $summary->earningsThisWeek,
            'earnings_last_week' => $summary->earningsLastWeek,
        ];
    }
}
