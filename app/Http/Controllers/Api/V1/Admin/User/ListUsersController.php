<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\User;

use App\Actions\Admin\User\ListUsersAction;
use App\Data\Admin\UserQueueData;
use App\Http\Requests\Api\V1\Admin\UserQueueRequest;
use App\Http\Resources\Api\V1\Admin\AdminUserResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListUsersController
{
    public function __construct(private readonly ListUsersAction $action) {}

    public function __invoke(UserQueueRequest $request): AnonymousResourceCollection
    {
        return AdminUserResource::collection(
            $this->action->handle(UserQueueData::fromRequest($request)),
        );
    }
}
