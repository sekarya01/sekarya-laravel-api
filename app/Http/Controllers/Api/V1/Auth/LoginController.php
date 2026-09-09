<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LoginAction;
use App\Data\Auth\LoginData;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Resources\Api\V1\TokenPairResource;

final class LoginController
{
    public function __construct(private readonly LoginAction $action) {}

    public function __invoke(LoginRequest $request): TokenPairResource
    {
        return new TokenPairResource(
            $this->action->handle(LoginData::fromRequest($request)),
        );
    }
}
