<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Resources\Api\V1\BlockedUserResource;
use App\Models\UserBlock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Daftar pengguna yang saya blokir (G7). Cursor by created_at. */
final class ListBlockedUsersController
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $page = UserBlock::query()
            ->where('blocker_id', $request->user()->getKey())
            ->with('blocked')
            ->orderBy('created_at')
            ->orderBy('id')
            ->cursorPaginate(20);

        return BlockedUserResource::collection($page);
    }
}
