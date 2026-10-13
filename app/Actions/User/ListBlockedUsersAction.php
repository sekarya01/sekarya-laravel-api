<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use App\Models\UserBlock;
use Illuminate\Contracts\Pagination\CursorPaginator;

/** Orang yang diblokir [blocker] (G7), urut waktu memblokir. */
final class ListBlockedUsersAction
{
    /** @return CursorPaginator<int, UserBlock> */
    public function handle(User $blocker, int $perPage = 20): CursorPaginator
    {
        return UserBlock::query()
            ->where('blocker_id', $blocker->getKey())
            ->with('blocked')
            ->latestFirst()
            ->cursorPaginate($perPage);
    }
}
