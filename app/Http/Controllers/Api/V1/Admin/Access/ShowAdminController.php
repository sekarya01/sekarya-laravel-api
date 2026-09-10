<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Access;

use App\Http\Resources\Api\V1\Admin\AdminResource;
use App\Models\Admin;

final class ShowAdminController
{
    public function __invoke(Admin $admin): AdminResource
    {
        return AdminResource::make($admin);
    }
}
