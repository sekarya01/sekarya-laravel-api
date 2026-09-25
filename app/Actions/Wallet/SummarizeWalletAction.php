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
 * Total masuk/keluar dan pendapatan mingguan dari buku besar.
 *
 * DIJUMLAHKAN BASIS DATA, bukan klien. Daftar `me/wallet/entries` bercursor
 * dan tidak punya `total`; menjumlahkan halaman yang kebetulan sudah dimuat
 * menghasilkan angka yang berubah setiap kali pengguna menggulir — dan angka
 * pendapatan di Beranda Mitra memang pernah salah karena itu.
 *
 * Kedua kueri bertumpu pada indeks `(wallet_id, created_at, id)`: rentang
 * waktu satu dompet, bukan pemindaian tabel.
 *
 * Jalur BACA: tidak membuat dompet. Orang tanpa dompet mendapat bentuk yang
 * sama, seluruhnya nol.
 */
final class SummarizeWalletAction
{
    public function handle(User $user, WalletSummaryQueryData $data, ?CarbonImmutable $now = null): WalletSummary
    {
        $walletId = $user->walletOrNew()->getKey();

        $byType = array_fill_keys(
            array_map(static fn (WalletEntryType $t): string => $t->value, WalletEntryType::cases()),
            0,
        );
        $count = 0;

        // Senin 00:00 di zona klien. Batas minggu milik ORANGNYA: Senin
        // 00:00 UTC adalah Senin 07:00 WIB, dan pendapatan Senin pagi akan
        // terhitung ke pekan lalu.
        $weekStart = ($now ?? CarbonImmutable::now())
            ->setTimezone($data->timezone)
            ->startOfWeek(CarbonImmutable::MONDAY);
        $lastWeekStart = $weekStart->subWeek();
        $nextWeekStart = $weekStart->addWeek();

        $thisWeek = 0;
        $lastWeek = 0;

        if ($walletId !== null) {
            $rows = WalletEntry::query()
                ->toBase()
                ->where('wallet_id', $walletId)
                ->where('created_at', '>=', $data->from->utc())
                ->where('created_at', '<', $data->to->utc())
                ->groupBy('type')
                ->selectRaw('type, SUM(amount) AS total, COUNT(*) AS entries')
                ->get();

            foreach ($rows as $row) {
                // Jenis yang tidak dikenal enum (baris lama yang jenisnya sudah
                // dihapus) tetap dihitung di `count`, tapi tidak bisa ditaruh di
                // kolom masuk/keluar karena arahnya tidak diketahui.
                $count += (int) $row->entries;

                if (array_key_exists((string) $row->type, $byType)) {
                    $byType[(string) $row->type] = (int) $row->total;
                }
            }

            $weeks = WalletEntry::query()
                ->toBase()
                ->where('wallet_id', $walletId)
                ->where('type', WalletEntryType::Earning->value)
                ->where('created_at', '>=', $lastWeekStart->utc())
                ->where('created_at', '<', $nextWeekStart->utc())
                ->selectRaw(
                    'COALESCE(SUM(CASE WHEN created_at >= ? THEN amount ELSE 0 END), 0) AS this_week, '
                    .'COALESCE(SUM(CASE WHEN created_at < ? THEN amount ELSE 0 END), 0) AS last_week',
                    [$weekStart->utc(), $weekStart->utc()],
                )
                ->first();

            $thisWeek = (int) ($weeks->this_week ?? 0);
            $lastWeek = (int) ($weeks->last_week ?? 0);
        }

        $totalIn = 0;
        $totalOut = 0;

        foreach (WalletEntryType::cases() as $type) {
            if ($type->direction() === WalletEntryDirection::Credit) {
                $totalIn += $byType[$type->value];
            } else {
                $totalOut += $byType[$type->value];
            }
        }

        return new WalletSummary(
            from: $data->from,
            to: $data->to,
            totalIn: $totalIn,
            totalOut: $totalOut,
            count: $count,
            byType: $byType,
            weekStart: $weekStart,
            earningsThisWeek: $thisWeek,
            earningsLastWeek: $lastWeek,
        );
    }
}
