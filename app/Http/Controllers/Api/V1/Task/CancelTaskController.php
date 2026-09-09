<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\CancelTaskAction;
use App\Http\Requests\Api\V1\Task\CancelTaskRequest;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;

final class CancelTaskController
{
    public function __construct(private readonly CancelTaskAction $action) {}

    public function __invoke(CancelTaskRequest $request, Task $task): TaskResource
    {
        return TaskResource::make(
            $this->action
                ->handle($task, $request->user(), $request->input('reason'))
                ->load(['category', 'poster', 'skills', 'payment']),
        );
    }
}
