<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Activity;

use App\Actions\Activity\ApproveActivityAction;
use App\Http\Requests\Api\V1\Activity\ReviewSubmissionRequest;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Activity;

/** Setujui hasil → task selesai → dana dilepas. */
final class ApproveActivityController
{
    public function __construct(private readonly ApproveActivityAction $action) {}

    public function __invoke(ReviewSubmissionRequest $request, Activity $activity): ActivityResource
    {
        return ActivityResource::make(
            $this->action
                ->handle($activity, $request->user(), $request->input('poster_note'))
                ->load(['task', 'payment', 'worker']),
        );
    }
}
