<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\RespondTaskCancelAction;
use App\Http\Resources\Api\V1\TaskCancelRequestResource;
use App\Models\Task;
use App\Models\TaskCancelRequest;

final class RejectTaskCancelController
{
    public function __construct(private readonly RespondTaskCancelAction $action) {}

    public function __invoke(Task $task, TaskCancelRequest $cancelRequest): TaskCancelRequestResource
    {
        $cancelRequest = $this->action
            ->reject($cancelRequest, request()->user())
            ->load(['task', 'approvals']);

        return TaskCancelRequestResource::make($cancelRequest);
    }
}
