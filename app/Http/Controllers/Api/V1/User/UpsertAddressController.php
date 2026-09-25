<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\UpsertAddressAction;
use App\Data\User\UpsertAddressData;
use App\Http\Requests\Api\V1\User\UpsertAddressRequest;
use App\Http\Resources\Api\V1\UserAddressResource;
use Illuminate\Http\JsonResponse;

final class UpsertAddressController
{
    public function __construct(private readonly UpsertAddressAction $action) {}

    public function __invoke(UpsertAddressRequest $request): JsonResponse
    {
        return UserAddressResource::make(
            $this->action->handle(UpsertAddressData::fromRequest($request), $request->user()),
        )->response()->setStatusCode(200);
    }
}
