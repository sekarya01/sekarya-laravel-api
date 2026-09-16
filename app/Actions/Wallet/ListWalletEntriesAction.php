<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Data\Wallet\WalletEntryQueryData;
use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Riwayat mutasi saldo seseorang.
 *
 * Berangkat dari `wallet_entries` yang disaring `wallet_id`, bukan lewat
 * relasi dari dompet yang mungkin belum ada. Orang yang belum pernah punya
 * mutasi tetap mendapat halaman kosong yang sah — bukan 404 atas dompet yang
 * memang belum perlu dibuat.
 */
final class ListWalletEntriesAction
{
    /** @return CursorPaginator<int, WalletEntry> */
    public function handle(User $user, WalletEntryQueryData $data): CursorPaginator
    {
        $walletId = $user->walletOrNew()->getKey();

        return WalletEntry::query()
            // Dompet yang belum ada tidak punya id, dan `where(col, null)`
            // diterjemahkan Eloquent jadi `col IS NULL` — pada kolom NOT NULL
            // itu tidak pernah cocok, jadi hasilnya halaman kosong yang sah.
            // Bukan 404: tidak punya riwayat bukan kesalahan.
            ->where('wallet_id', $walletId)
            ->when($data->type !== null, fn ($q) => $q->where('type', $data->type))
            ->when($data->direction !== null, fn ($q) => $q->where('direction', $data->direction))
            ->latestFirst()
            ->cursorPaginate($data->page->perPage);
    }
}
