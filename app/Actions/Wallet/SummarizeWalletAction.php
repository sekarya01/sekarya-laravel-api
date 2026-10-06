<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Data\Wallet\WalletSummary;
use App\Data\Wallet\WalletSummaryQueryData;
use App\Enums\WalletEntryDirection;
use App\Enums\WalletEntryType;
use App\Enums\WalletSummaryGroup;
use App\Models\Activity;
use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use App\Models\WalletEntry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;

/**
 * Total masuk/keluar dari buku besar (B2) — DIJUMLAHKAN BASIS DATA, bukan
 * klien. Daftar `me/wallet/entries` bercursor dan tidak punya `total`;
 * menjumlahkan halaman yang kebetulan sudah dimuat menghasilkan angka yang
 * berubah setiap kali pengguna menggulir.
 *
 * Kontrak dokumen: `credit_total`, `debit_total`, `entries_count`, `by_type`,
 * `earning_total`, `earning_count`; `previous` bila `compare_previous=1`;
 * `by_day`/`by_week`/`by_month` bila `group=day|week|month`; `by_category`
 * bila `with_categories=1`. Penyaring `types[]`/`direction` membuat angka
 * mengikuti tab yang sedang dibuka.
 *
 * Semua kueri bertumpu pada indeks `(wallet_id, created_at, id)`: rentang
 * waktu satu dompet, bukan pemindaian tabel. Jalur BACA: tidak membuat dompet
 * — orang tanpa dompet mendapat bentuk yang sama, seluruhnya nol.
 */
final class SummarizeWalletAction
{
    /** Kategori penampung pendapatan yang task/kategorinya tidak bisa dirunut. */
    public const string FALLBACK_CATEGORY_SLUG = 'lainnya';

    public const string FALLBACK_CATEGORY_NAME = 'Lainnya';

    public function handle(User $user, WalletSummaryQueryData $data): WalletSummary
    {
        $walletId = $user->walletOrNew()->getKey();

        $current = $this->totalsFor($walletId, $data->from, $data->to, $data);

        $previous = null;

        if ($data->comparePrevious) {
            // Periode sebelumnya = panjang yang sama, tepat SEBELUM `from`.
            // Dihitung dari rentang yang diminta, bukan "pekan kalender",
            // supaya rumusnya sama untuk rentang apa pun (minggu, bulan, …).
            $span = $data->from->diffInSeconds($data->to);
            $previous = $this->totalsFor($walletId, $data->from->subSeconds($span), $data->from, $data);
        }

        $buckets = $data->group === null ? [] : $this->bucketsFor($walletId, $data);

        return new WalletSummary(
            from: $data->from,
            to: $data->to,
            creditTotal: $current['credit'],
            debitTotal: $current['debit'],
            entriesCount: $current['count'],
            byType: $current['byType'],
            earningTotal: $current['earning'],
            earningCount: $current['earningCount'],
            previousCreditTotal: $previous['credit'] ?? null,
            previousEarningTotal: $previous['earning'] ?? null,
            previousEarningCount: $previous['earningCount'] ?? null,
            byMonth: $data->group === WalletSummaryGroup::Month ? $buckets : [],
            byDay: $data->group === WalletSummaryGroup::Day ? $buckets : null,
            byWeek: $data->group === WalletSummaryGroup::Week ? $buckets : null,
            byCategory: $data->withCategories ? $this->categoriesFor($walletId, $data) : null,
        );
    }

    /**
     * Baris satu dompet dalam satu rentang, dengan penyaring `types[]` dan
     * `direction` — dasar bersama seluruh kueri ringkasan, supaya setiap
     * angka menghitung himpunan baris yang sama persis.
     */
    private function rangeQuery(
        int $walletId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        WalletSummaryQueryData $data,
    ): Builder {
        $table = (new WalletEntry)->getTable();

        return WalletEntry::query()
            ->toBase()
            ->where($table.'.wallet_id', $walletId)
            ->where($table.'.created_at', '>=', $from->utc())
            ->where($table.'.created_at', '<', $to->utc())
            ->when($data->types !== [], fn (Builder $q) => $q->whereIn(
                $table.'.type',
                array_map(static fn (WalletEntryType $t): string => $t->value, $data->types),
            ))
            ->when($data->direction !== null, fn (Builder $q) => $q->where($table.'.direction', $data->direction?->value));
    }

    /**
     * Satu pass `GROUP BY type` untuk satu rentang.
     *
     * Jenis yang tidak dikenal enum (baris lama yang jenisnya sudah dihapus)
     * tetap dihitung di `count`, tapi tidak bisa ditaruh di kolom masuk/keluar
     * karena arahnya tidak diketahui.
     *
     * @return array{byType: array<string, int>, count: int, credit: int, debit: int, earning: int, earningCount: int}
     */
    private function totalsFor(
        ?int $walletId,
        CarbonImmutable $from,
        CarbonImmutable $to,
        WalletSummaryQueryData $data,
    ): array {
        $byType = array_fill_keys(
            array_map(static fn (WalletEntryType $t): string => $t->value, WalletEntryType::cases()),
            0,
        );
        $count = 0;
        $earningCount = 0;

        if ($walletId !== null) {
            $rows = $this->rangeQuery($walletId, $from, $to, $data)
                ->groupBy('type')
                ->selectRaw('type, SUM(amount) AS total, COUNT(*) AS entries')
                ->get();

            foreach ($rows as $row) {
                $count += (int) $row->entries;

                if (array_key_exists((string) $row->type, $byType)) {
                    $byType[(string) $row->type] = (int) $row->total;
                }

                if ((string) $row->type === WalletEntryType::Earning->value) {
                    $earningCount = (int) $row->entries;
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
            'earningCount' => $earningCount,
        ];
    }

    /**
     * Deret per hari / pekan (mulai Senin) / bulan kalender, memakai offset
     * `from` (klien yang mengirim `+07:00` mendapat hari versi WIB, bukan UTC).
     *
     * Dibucket di PHP karena `created_at` tersimpan UTC dan `CONVERT_TZ`
     * menuntut tabel zona waktu yang tidak dijamin ada di shared hosting.
     *
     * `day`/`week` DIISI NOL: setiap hari/pekan yang menyentuh [from, to)
     * muncul, urut naik — termasuk untuk akun tanpa dompet. Pekan pertama
     * bisa bermula sebelum `from` (Senin pekan yang memuat `from`). `month`
     * mempertahankan perilaku lama: hanya bulan yang punya baris, dan daftar
     * kosong bila belum ada dompet.
     *
     * @return list<array<string, int|string>>
     */
    private function bucketsFor(?int $walletId, WalletSummaryQueryData $data): array
    {
        /** @var WalletSummaryGroup $group */
        $group = $data->group;
        $timezone = $data->from->getTimezone();

        [$key, $format, $start, $step] = match ($group) {
            WalletSummaryGroup::Day => ['date', 'Y-m-d', static fn (CarbonImmutable $at): CarbonImmutable => $at->startOfDay(), 'addDay'],
            WalletSummaryGroup::Week => ['week_start', 'Y-m-d', static fn (CarbonImmutable $at): CarbonImmutable => $at->startOfWeek(CarbonInterface::MONDAY), 'addWeek'],
            WalletSummaryGroup::Month => ['month', 'Y-m', static fn (CarbonImmutable $at): CarbonImmutable => $at->startOfMonth(), 'addMonthNoOverflow'],
        };

        $empty = static fn (string $label): array => [
            $key => $label,
            'credit_total' => 0,
            'debit_total' => 0,
            'entries_count' => 0,
            'earning_total' => 0,
            'earning_count' => 0,
        ];

        $buckets = [];

        if ($group !== WalletSummaryGroup::Month) {
            for ($at = $start($data->from); $at->lt($data->to); $at = $at->{$step}()) {
                $buckets[$at->format($format)] = $empty($at->format($format));
            }
        }

        if ($walletId === null) {
            return array_values($buckets);
        }

        $rows = $this->rangeQuery($walletId, $data->from, $data->to, $data)
            ->selectRaw('type, direction, amount, created_at')
            ->get();

        foreach ($rows as $row) {
            $local = CarbonImmutable::parse((string) $row->created_at, 'UTC')->setTimezone($timezone);
            $label = $start($local)->format($format);
            $bucket = $buckets[$label] ?? $empty($label);

            $amount = (int) $row->amount;
            $bucket['entries_count']++;

            if ((string) $row->direction === WalletEntryDirection::Credit->value) {
                $bucket['credit_total'] += $amount;

                if ((string) $row->type === WalletEntryType::Earning->value) {
                    $bucket['earning_total'] += $amount;
                    $bucket['earning_count']++;
                }
            } else {
                $bucket['debit_total'] += $amount;
            }

            $buckets[$label] = $bucket;
        }

        ksort($buckets);

        return array_values($buckets);
    }

    /**
     * Pendapatan (`earning`) per kategori task — SATU kueri ber-`GROUP BY`.
     *
     * Baris `earning` hanya lahir dari `WorkerPayout`, yang merujuk
     * `activities`; dari sana ke task lalu ke kategorinya. Join mentah
     * sengaja tidak menyaring `deleted_at`: riwayat uang tidak boleh
     * kehilangan kategorinya karena task-nya dihapus lunak.
     *
     * Baris tanpa task/kategori masuk ke "Lainnya" (ikon `null`); bila
     * kategori asli `lainnya` juga punya baris, keduanya digabung dan nama/
     * ikon kategori asli yang dipakai. Urut `earning_total` turun lalu `name`
     * naik; hanya kategori dengan total > 0.
     *
     * @return list<array{slug: string, name: string, icon: string|null, earning_total: int, earning_count: int}>
     */
    private function categoriesFor(?int $walletId, WalletSummaryQueryData $data): array
    {
        if ($walletId === null) {
            return [];
        }

        $entries = (new WalletEntry)->getTable();
        $activities = (new Activity)->getTable();
        $tasks = (new Task)->getTable();
        $categories = (new Category)->getTable();

        $rows = $this->rangeQuery($walletId, $data->from, $data->to, $data)
            ->where($entries.'.type', WalletEntryType::Earning->value)
            ->leftJoin($activities, function ($join) use ($entries, $activities): void {
                $join->on($activities.'.id', '=', $entries.'.reference_id')
                    ->where($entries.'.reference_type', '=', $activities);
            })
            ->leftJoin($tasks, $tasks.'.id', '=', $activities.'.task_id')
            ->leftJoin($categories, $categories.'.id', '=', $tasks.'.category_id')
            ->groupBy($categories.'.id', $categories.'.slug', $categories.'.name', $categories.'.icon')
            ->selectRaw(
                "{$categories}.slug AS slug, {$categories}.name AS name, {$categories}.icon AS icon, "
                ."SUM({$entries}.amount) AS total, COUNT(*) AS entries",
            )
            ->get();

        $byCategory = [];

        foreach ($rows as $row) {
            $slug = $row->slug === null ? self::FALLBACK_CATEGORY_SLUG : (string) $row->slug;
            $item = $byCategory[$slug] ?? [
                'slug' => $slug,
                'name' => self::FALLBACK_CATEGORY_NAME,
                'icon' => null,
                'earning_total' => 0,
                'earning_count' => 0,
            ];

            if ($row->slug !== null) {
                $item['name'] = (string) $row->name;
                $item['icon'] = $row->icon === null ? null : (string) $row->icon;
            }

            $item['earning_total'] += (int) $row->total;
            $item['earning_count'] += (int) $row->entries;
            $byCategory[$slug] = $item;
        }

        $list = array_values(array_filter(
            $byCategory,
            static fn (array $item): bool => $item['earning_total'] > 0,
        ));

        usort($list, static fn (array $a, array $b): int => [$b['earning_total'], $a['name']] <=> [$a['earning_total'], $b['name']]);

        return $list;
    }
}
