<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\ListTasksAction;
use App\Data\Task\ListTasksData;
use App\Http\Requests\Api\V1\Task\ListTasksRequest;
use App\Http\Resources\Api\V1\TaskResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListMyWorkedTasksController
{
    public function __construct(private readonly ListTasksAction $action) {}

    public function __invoke(ListTasksRequest $request): AnonymousResourceCollection
    {
        return TaskResource::collection(
            $this->action->workedBy(ListTasksData::fromRequest($request), $request->user()),
        );
    }
}
