<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Http\Resources\Api\V1\Admin\AdminResource;
use Illuminate\Http\Request;

final class ShowAdminMeController
{
    public function __invoke(Request $request): AdminResource
    {
        return AdminResource::make($request->user());
    }
}
