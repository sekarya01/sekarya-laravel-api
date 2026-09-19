<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Activity;

use App\Actions\Activity\DepartActivityAction;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Activity;

/** Pekerja berangkat ke lokasi. */
final class DepartActivityController
{
    public function __construct(private readonly DepartActivityAction $action) {}

    public function __invoke(Activity $activity): ActivityResource
    {
        return ActivityResource::make($this->action->handle($activity)->load('payment'));
    }
}
