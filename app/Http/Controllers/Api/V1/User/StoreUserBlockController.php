<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\BlockUserAction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** Blokir pengguna (G7). Idempoten. */
final class StoreUserBlockController
{
    public function __construct(private readonly BlockUserAction $action) {}

    public function __invoke(Request $request, string $user): Response
    {
        $blocked = User::query()->where('ulid', $user)->firstOrFail();

        $this->action->handle($request->user(), $blocked);

        return response()->noContent();
    }
}
