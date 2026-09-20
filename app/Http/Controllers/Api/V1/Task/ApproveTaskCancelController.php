<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\RespondTaskCancelAction;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;
use App\Models\TaskCancelRequest;

final class ApproveTaskCancelController
{
    public function __construct(private readonly RespondTaskCancelAction $action) {}

    public function __invoke(Task $task, TaskCancelRequest $cancelRequest): TaskResource
    {
        $task = $this->action->approve($task, $cancelRequest, request()->user());

        return TaskResource::make(
            $task->load(['category', 'poster', 'skills', 'payment']),
        );
    }
}
