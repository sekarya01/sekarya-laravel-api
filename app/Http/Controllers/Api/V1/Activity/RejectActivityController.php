<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Activity;

use App\Actions\Activity\RejectActivityAction;
use App\Http\Requests\Api\V1\Activity\ReviewSubmissionRequest;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Activity;

/** Tolak hasil → sengketa. Dana TETAP ditahan. */
final class RejectActivityController
{
    public function __construct(private readonly RejectActivityAction $action) {}

    public function __invoke(ReviewSubmissionRequest $request, Activity $activity): ActivityResource
    {
        return ActivityResource::make(
            $this->action
                ->handle($activity, $request->user(), $request->input('poster_note'))
                ->load(['task', 'payment']),
        );
    }
}
