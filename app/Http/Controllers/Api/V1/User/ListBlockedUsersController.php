<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\ListBlockedUsersAction;
use App\Http\Resources\Api\V1\BlockedUserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Daftar pengguna yang saya blokir (G7). Cursor by created_at. */
final class ListBlockedUsersController
{
    public function __construct(private readonly ListBlockedUsersAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return BlockedUserResource::collection($this->action->handle($request->user()));
    }
}
