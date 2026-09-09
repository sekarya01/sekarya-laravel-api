<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Activity;

use App\Actions\Activity\SubmitActivityAction;
use App\Data\Activity\SubmitActivityData;
use App\Http\Requests\Api\V1\Activity\SubmitActivityRequest;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Activity;

final class SubmitActivityController
{
    public function __construct(private readonly SubmitActivityAction $action) {}

    public function __invoke(SubmitActivityRequest $request, Activity $activity): ActivityResource
    {
        return ActivityResource::make(
            $this->action
                ->handle(SubmitActivityData::fromRequest($request), $activity)
                ->load(['task', 'payment']),
        );
    }
}
