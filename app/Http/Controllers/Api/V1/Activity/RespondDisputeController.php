<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Activity;

use App\Actions\Activity\RespondDisputeAction;
use App\Data\Activity\RespondDisputeData;
use App\Http\Requests\Api\V1\Activity\RespondDisputeRequest;
use App\Http\Resources\Api\V1\TaskDisputeResource;
use App\Models\Activity;

/** Mitra menanggapi sengketa atas hasil kerjanya (sekali). */
final class RespondDisputeController
{
    public function __construct(private readonly RespondDisputeAction $action) {}

    public function __invoke(RespondDisputeRequest $request, Activity $activity): TaskDisputeResource
    {
        return TaskDisputeResource::make(
            $this->action->handle(RespondDisputeData::fromRequest($request), $activity)->load(['activity', 'task']),
        );
    }
}
