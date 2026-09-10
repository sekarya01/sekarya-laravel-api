<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Admin\Auth\AdminLoginAction;
use App\Data\Admin\AdminLoginData;
use App\Http\Requests\Api\V1\Admin\AdminLoginRequest;
use App\Http\Resources\Api\V1\Admin\AdminTokenPairResource;

final class AdminLoginController
{
    public function __construct(private readonly AdminLoginAction $action) {}

    public function __invoke(AdminLoginRequest $request): AdminTokenPairResource
    {
        return new AdminTokenPairResource(
            $this->action->handle(AdminLoginData::fromRequest($request)),
        );
    }
}
