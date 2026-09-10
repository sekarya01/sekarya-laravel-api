<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Admin\Auth\RefreshAdminTokenAction;
use App\Http\Resources\Api\V1\AccessTokenResource;
use Illuminate\Http\Request;

/**
 * Dilindungi `abilities:admin:refresh`, jadi access token pengelola tidak
 * bisa memanggilnya — hanya long_lived milik pengelola yang bisa.
 */
final class AdminRefreshTokenController
{
    public function __construct(private readonly RefreshAdminTokenAction $action) {}

    public function __invoke(Request $request): AccessTokenResource
    {
        return new AccessTokenResource($this->action->handle($request->user()));
    }
}
