<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Activity;

use App\Actions\Activity\ListActivitiesAction;
use App\Http\Requests\Api\V1\Activity\ListMyActivitiesRequest;
use App\Http\Resources\Api\V1\ActivityResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListMyActivitiesController
{
    public function __construct(private readonly ListActivitiesAction $action) {}

    public function __invoke(ListMyActivitiesRequest $request): AnonymousResourceCollection
    {
        return ActivityResource::collection(
            $this->action->forWorker($request->user(), $request->page()),
        );
    }
}
