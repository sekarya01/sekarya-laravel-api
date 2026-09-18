<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\UpdateTaskAction;
use App\Data\Task\UpdateTaskData;
use App\Http\Requests\Api\V1\Task\UpdateTaskRequest;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;

final class UpdateTaskController
{
    public function __construct(private readonly UpdateTaskAction $action) {}

    public function __invoke(UpdateTaskRequest $request, Task $task): TaskResource
    {
        return TaskResource::make(
            $this->action
                ->handle($task, UpdateTaskData::fromRequest($request))
                ->load(['category', 'poster', 'skills', 'payment', 'activities.worker']),
        );
    }
}
