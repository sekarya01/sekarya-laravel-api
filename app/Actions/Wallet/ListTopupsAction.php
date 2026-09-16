<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Data\CursorPageData;
use App\Enums\WalletTopupStatus;
use App\Models\User;
use App\Models\WalletTopup;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class ListTopupsAction
{
    /** @return CursorPaginator<int, WalletTopup> */
    public function handle(User $user, CursorPageData $page, ?WalletTopupStatus $status = null): CursorPaginator
    {
        return WalletTopup::query()
            ->where('user_id', $user->getKey())
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->latestFirst()
            ->cursorPaginate($page->perPage);
    }
}
