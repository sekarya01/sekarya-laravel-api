<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Data\Wallet\WalletSummary;
use Illuminate\Http\Request;

/**
 * Ringkasan saldo sendiri (B2). Seluruh angka bilangan bulat rupiah.
 *
 * Nama field mengikuti kontrak dokumen redesign: `credit_total`,
 * `debit_total`, `entries_count`, `by_type`, `earning_total`. `previous`
 * hanya muncul bila diminta (`compare_previous=1`); `by_month` hanya bila
 * `group=month`.
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

        $data = [
            'from' => $this->iso($summary->from),
            'to' => $this->iso($summary->to),
            'credit_total' => $summary->creditTotal,
            'debit_total' => $summary->debitTotal,
            'entries_count' => $summary->entriesCount,
            'by_type' => (object) $summary->byType,
            'earning_total' => $summary->earningTotal,
        ];

        if ($summary->previousCreditTotal !== null) {
            $data['previous'] = [
                'credit_total' => $summary->previousCreditTotal,
                'earning_total' => $summary->previousEarningTotal ?? 0,
            ];
        }

        if ($summary->byMonth !== []) {
            $data['by_month'] = $summary->byMonth;
        }

        return $data;
    }
}
