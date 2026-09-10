<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\User;

use App\Actions\Admin\User\ChangeUserStatusAction;
use App\Data\Admin\ChangeUserStatusData;
use App\Http\Requests\Api\V1\Admin\ModerateUserRequest;
use App\Http\Resources\Api\V1\Admin\AdminUserResource;
use App\Models\User;

final class BanUserController
{
    public function __construct(private readonly ChangeUserStatusAction $action) {}

    public function __invoke(ModerateUserRequest $request, User $user): AdminUserResource
    {
        return AdminUserResource::make(
            $this->action->handle($user, $request->user(), ChangeUserStatusData::ban($request)),
        );
    }
}
