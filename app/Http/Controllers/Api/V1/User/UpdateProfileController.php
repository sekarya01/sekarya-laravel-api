<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\UpdateProfileAction;
use App\Data\User\UpdateProfileData;
use App\Http\Requests\Api\V1\User\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;

final class UpdateProfileController
{
    public function __construct(private readonly UpdateProfileAction $action) {}

    public function __invoke(UpdateProfileRequest $request): UserResource
    {
        return UserResource::make(
            $this->action->handle(UpdateProfileData::fromRequest($request), $request->user()),
        );
    }
}
