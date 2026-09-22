<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\RegisterDeviceTokenAction;
use App\Data\User\RegisterDeviceData;
use App\Http\Requests\Api\V1\User\RegisterDeviceRequest;
use App\Http\Resources\Api\V1\DeviceTokenResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class RegisterDeviceController
{
    public function __construct(private readonly RegisterDeviceTokenAction $action) {}

    public function __invoke(RegisterDeviceRequest $request): JsonResponse
    {
        $device = $this->action->handle(
            RegisterDeviceData::fromRequest($request),
            $request->user(),
        );

        return DeviceTokenResource::make($device)
            ->response()
            ->setStatusCode(
                $device->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK,
            );
    }
}
