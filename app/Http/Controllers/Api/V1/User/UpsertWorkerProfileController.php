<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\UpsertWorkerProfileAction;
use App\Data\User\UpsertWorkerProfileData;
use App\Http\Requests\Api\V1\User\UpsertWorkerProfileRequest;
use App\Http\Resources\Api\V1\WorkerProfileResource;

final class UpsertWorkerProfileController
{
    public function __construct(private readonly UpsertWorkerProfileAction $action) {}

    public function __invoke(UpsertWorkerProfileRequest $request): WorkerProfileResource
    {
        return WorkerProfileResource::make(
            $this->action->handle(
                UpsertWorkerProfileData::fromRequest($request),
                $request->user(),
            ),
        );
    }
}
