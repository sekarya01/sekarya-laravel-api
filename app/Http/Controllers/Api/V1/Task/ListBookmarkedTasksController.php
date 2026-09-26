<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\ListBookmarkedTasksAction;
use App\Data\Task\ListTasksData;
use App\Http\Requests\Api\V1\Task\ListTasksRequest;
use App\Http\Resources\Api\V1\TaskResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Tugas yang saya simpan (B11). */
final class ListBookmarkedTasksController
{
    public function __construct(private readonly ListBookmarkedTasksAction $action) {}

    public function __invoke(ListTasksRequest $request): AnonymousResourceCollection
    {
        return TaskResource::collection(
            $this->action->handle(ListTasksData::fromRequest($request), $request->user()),
        );
    }
}
