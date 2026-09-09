<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\Request;

final class ShowMeController
{
    public function __invoke(Request $request): UserResource
    {
        return UserResource::make($request->user()->load('skills'));
    }
}
