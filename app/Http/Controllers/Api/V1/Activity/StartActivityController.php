<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Activity;

use App\Actions\Activity\StartActivityAction;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Activity;

final class StartActivityController
{
    public function __construct(private readonly StartActivityAction $action) {}

    public function __invoke(Activity $activity): ActivityResource
    {
        return ActivityResource::make($this->action->handle($activity)->load('payment'));
    }
}
