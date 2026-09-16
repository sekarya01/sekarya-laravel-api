<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Data\CursorPageData;
use App\Enums\WalletWithdrawalStatus;
use App\Models\User;
use App\Models\WalletWithdrawal;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class ListWithdrawalsAction
{
    /** @return CursorPaginator<int, WalletWithdrawal> */
    public function handle(User $user, CursorPageData $page, ?WalletWithdrawalStatus $status = null): CursorPaginator
    {
        return WalletWithdrawal::query()
            ->where('user_id', $user->getKey())
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            // `verification` ikut supaya daftarnya bisa menyebut bank tujuan
            // tanpa satu kueri per baris. Nomor rekeningnya tidak ikut keluar —
            // Resource-nya hanya membaca `bank_code` dan nama pemilik.
            ->with('verification')
            ->latestFirst()
            ->cursorPaginate($page->perPage);
    }
}
