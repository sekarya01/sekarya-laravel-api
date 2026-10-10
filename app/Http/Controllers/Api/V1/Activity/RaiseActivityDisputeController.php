<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Activity;

use App\Actions\Activity\RaiseActivityDisputeAction;
use App\Data\Activity\RaiseActivityDisputeData;
use App\Http\Requests\Api\V1\Activity\RaiseActivityDisputeRequest;
use App\Http\Resources\Api\V1\TaskDisputeResource;
use App\Models\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** Pemberi kerja menyengketakan hasil satu mitra. */
final class RaiseActivityDisputeController
{
    public function __construct(private readonly RaiseActivityDisputeAction $action) {}

    public function __invoke(RaiseActivityDisputeRequest $request, Activity $activity): JsonResponse
    {
        $dispute = $this->action->handle(RaiseActivityDisputeData::fromRequest($request), $activity, $request->user());

        return TaskDisputeResource::make($dispute->load(['activity', 'task']))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
