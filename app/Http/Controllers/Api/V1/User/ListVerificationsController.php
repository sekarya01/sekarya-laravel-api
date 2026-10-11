<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\ListMyVerificationsAction;
use App\Http\Resources\Api\V1\VerificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListVerificationsController
{
    public function __construct(private readonly ListMyVerificationsAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return VerificationResource::collection($this->action->handle($request->user()));
    }
}
