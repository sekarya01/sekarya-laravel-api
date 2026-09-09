<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RefreshAccessTokenAction;
use App\Http\Resources\Api\V1\AccessTokenResource;
use Illuminate\Http\Request;

/**
 * Tukar long_lived token jadi access token baru.
 *
 * Rute ini dilindungi `abilities:token:refresh`, jadi access token biasa
 * TIDAK bisa memanggilnya — hanya long_lived token yang bisa.
 */
final class RefreshTokenController
{
    public function __construct(private readonly RefreshAccessTokenAction $action) {}

    public function __invoke(Request $request): AccessTokenResource
    {
        return new AccessTokenResource($this->action->handle($request->user()));
    }
}
