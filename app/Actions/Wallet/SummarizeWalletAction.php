<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Data\Wallet\WalletSummary;
use App\Data\Wallet\WalletSummaryQueryData;
use App\Enums\WalletEntryDirection;
use App\Enums\WalletEntryType;
use App\Models\User;
use App\Models\WalletEntry;
use Carbon\CarbonImmutable;

/**
 * Total masuk/keluar dari buku besar (B2) — DIJUMLAHKAN BASIS DATA, bukan
 * klien. Daftar `me/wallet/entries` bercursor dan tidak punya `total`;
 * menjumlahkan halaman yang kebetulan sudah dimuat menghasilkan angka yang
 * berubah setiap kali pengguna menggulir.
 *
 * Kontrak dokumen: `credit_total`, `debit_total`, `entries_count`, `by_type`,
 * `earning_total`; `previous` bila `compare_previous=1`; `by_month` bila
 * `group=month`. Penyaring `types[]`/`direction` membuat angka mengikuti tab
 * yang sedang dibuka.
 *
 * Semua kueri bertumpu pada indeks `(wallet_id, created_at, id)`: rentang
 * waktu satu dompet, bukan pemindaian tabel. Jalur BACA: tidak membuat dompet
 * — orang tanpa dompet mendapat bentuk yang sama, seluruhnya nol.
 */
final class SummarizeWalletAction
{
    public function handle(User $user, WalletSummaryQueryData $data): WalletSummary
    {
        $walletId = $user->walletOrNew()->getKey();

        $current = $this->totalsFor($walletId, $data->from, $data->to, $data->types, $data->direction);

        $previousCredit = null;
        $previousEarning = null;

        if ($data->comparePrevious) {
            // Periode sebelumnya = panjang yang sama, tepat SEBELUM `from`.
            // Dihitung dari rentang yang diminta, bukan "pekan kalender",
            // supaya rumusnya sama untuk rentang apa pun (minggu, bulan, …).
            $span = $data->from->diffInSeconds($data->to);
            $previous = $this->totalsFor(
                $walletId,
                $data->from->subSeconds($span),
                $data->from,
                $data->types,
                $data->direction,
            );

            $previousCredit = $previous['credit'];
            $previousEarning = $previous['earning'];
        }

        return new WalletSummary(
            from: $data->from,
            to: $data->to,
            creditTotal: $current['credit'],
            debitTotal: $current['debit'],
            entriesCount: $current['count'],
            byType: $current['byType'],
            earningTotal: $current['earning'],
            previousCreditTotal: $previousCredit,
            previousEarningTotal: $previousEarning,
            byMonth: $data->groupByMonth
                ? $this->monthsFor($walletId, $data->from, $data->to, $data->types, $data->direction)
                : [],
        );
    }

    /**
     * Satu pass `GROUP BY type` untuk satu rentang.
     *
     * Jenis yang tidak dikenal enum (baris lama yang jenisnya sudah dihapus)
     * tetap dihitung di `count`, tapi tidak bisa ditaruh di kolom masuk/keluar
     * karena arahnya tidak diketahui.
     *
     * @param  list<WalletEntryType>  $types
     * @return array{byType: array<string, int>, count: int, credit: int, debit: int, earning: int}
     */
    private function totalsFor(
        ?int $walletId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $types,
        ?WalletEntryDirection $direction,
    ): array {
        $byType = array_fill_keys(
            array_map(static fn (WalletEntryType $t): string => $t->value, WalletEntryType::cases()),
            0,
        );
        $count = 0;

        if ($walletId !== null) {
            $rows = WalletEntry::query()
                ->toBase()
                ->where('wallet_id', $walletId)
                ->where('created_at', '>=', $from->utc())
                ->where('created_at', '<', $to->utc())
                ->when($types !== [], fn ($q) => $q->whereIn(
                    'type',
                    array_map(static fn (WalletEntryType $t): string => $t->value, $types),
                ))
                ->when($direction !== null, fn ($q) => $q->where('direction', $direction->value))
                ->groupBy('type')
                ->selectRaw('type, SUM(amount) AS total, COUNT(*) AS entries')
                ->get();

            foreach ($rows as $row) {
                $count += (int) $row->entries;

                if (array_key_exists((string) $row->type, $byType)) {
                    $byType[(string) $row->type] = (int) $row->total;
                }
            }
        }

        $credit = 0;
        $debit = 0;

        foreach (WalletEntryType::cases() as $type) {
            if ($type->direction() === WalletEntryDirection::Credit) {
                $credit += $byType[$type->value];
            } else {
                $debit += $byType[$type->value];
            }
        }

        return [
            'byType' => $byType,
            'count' => $count,
            'credit' => $credit,
            'debit' => $debit,
            'earning' => $byType[WalletEntryType::Earning->value],
        ];
    }

    /**
     * Rincian per bulan kalender, memakai offset `from` (klien yang mengirim
     * `+07:00` mendapat bulan versi WIB, bukan UTC).
     *
     * Dibucket di PHP karena `created_at` tersimpan UTC dan `CONVERT_TZ`
     * menuntut tabel zona waktu yang tidak dijamin ada di shared hosting.
     * Hanya dijalankan saat `group=month` diminta.
     *
     * @param  list<WalletEntryType>  $types
     * @return list<array{month: string, credit_total: int, debit_total: int, entries_count: int, earning_total: int}>
     */
    private function monthsFor(
        ?int $walletId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        array $types,
        ?WalletEntryDirection $direction,
    ): array {
        if ($walletId === null) {
            return [];
        }

        $rows = WalletEntry::query()
            ->toBase()
            ->where('wallet_id', $walletId)
            ->where('created_at', '>=', $from->utc())
            ->where('created_at', '<', $to->utc())
            ->when($types !== [], fn ($q) => $q->whereIn(
                'type',
                array_map(static fn (WalletEntryType $t): string => $t->value, $types),
            ))
            ->when($direction !== null, fn ($q) => $q->where('direction', $direction->value))
            ->selectRaw('type, direction, amount, created_at')
            ->get();

        $timezone = $from->getTimezone();
        $buckets = [];

        foreach ($rows as $row) {
            $month = CarbonImmutable::parse((string) $row->created_at)->setTimezone($timezone)->format('Y-m');
            $bucket = $buckets[$month] ??= [
                'month' => $month,
                'credit_total' => 0,
                'debit_total' => 0,
                'entries_count' => 0,
                'earning_total' => 0,
            ];

            $amount = (int) $row->amount;
            $bucket['entries_count']++;

            if ((string) $row->direction === WalletEntryDirection::Credit->value) {
                $bucket['credit_total'] += $amount;

                if ((string) $row->type === WalletEntryType::Earning->value) {
                    $bucket['earning_total'] += $amount;
                }
            } else {
                $bucket['debit_total'] += $amount;
            }

            $buckets[$month] = $bucket;
        }

        ksort($buckets);

        return array_values($buckets);
    }
}
