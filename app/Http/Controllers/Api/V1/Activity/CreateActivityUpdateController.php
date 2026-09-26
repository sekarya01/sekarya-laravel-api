<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Activity;

use App\Actions\Activity\CreateActivityUpdateAction;
use App\Http\Requests\Api\V1\Activity\CreateActivityUpdateRequest;
use App\Http\Resources\Api\V1\ActivityUpdateResource;
use App\Models\Activity;

final class CreateActivityUpdateController
{
    public function __construct(private readonly CreateActivityUpdateAction $action) {}

    public function __invoke(CreateActivityUpdateRequest $request, Activity $activity): ActivityUpdateResource
    {
        return ActivityUpdateResource::make($this->action->handle(
            $activity,
            trim($request->string('note')->value()),
            $request->filled('photo') ? $request->string('photo')->value() : null,
        ));
    }
}
