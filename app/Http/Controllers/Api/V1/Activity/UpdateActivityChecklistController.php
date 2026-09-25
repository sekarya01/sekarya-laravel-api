<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Activity;

use App\Actions\Activity\UpdateActivityChecklistAction;
use App\Http\Requests\Api\V1\Activity\UpdateActivityChecklistRequest;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Activity;

final class UpdateActivityChecklistController
{
    public function __construct(private readonly UpdateActivityChecklistAction $action) {}

    public function __invoke(UpdateActivityChecklistRequest $request, Activity $activity): ActivityResource
    {
        $updated = $this->action->handle($activity, $request->array('state'));

        return ActivityResource::make($updated->load(['task', 'worker']));
    }
}
