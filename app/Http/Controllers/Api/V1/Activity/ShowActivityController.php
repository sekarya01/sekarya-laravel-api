<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Activity;

use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Activity;

final class ShowActivityController
{
    public function __invoke(Activity $activity): ActivityResource
    {
        return ActivityResource::make(
            $activity->load(['task.category', 'task.poster', 'worker', 'payment']),
        );
    }
}
