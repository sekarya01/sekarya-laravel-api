<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Activity;

use App\Actions\Activity\UpdateActivityLocationAction;
use App\Http\Requests\Api\V1\Activity\UpdateActivityLocationRequest;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Activity;

final class UpdateActivityLocationController
{
    public function __construct(private readonly UpdateActivityLocationAction $action) {}

    public function __invoke(UpdateActivityLocationRequest $request, Activity $activity): ActivityResource
    {
        $updated = $this->action->handle(
            $activity,
            $request->float('latitude'),
            $request->float('longitude'),
        );

        return ActivityResource::make($updated->load(['task', 'worker']));
    }
}
