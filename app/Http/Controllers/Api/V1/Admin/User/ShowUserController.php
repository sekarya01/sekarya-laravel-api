<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\User;

use App\Http\Resources\Api\V1\Admin\AdminUserResource;
use App\Models\User;

final class ShowUserController
{
    public function __invoke(User $user): AdminUserResource
    {
        return AdminUserResource::make($user);
    }
}
