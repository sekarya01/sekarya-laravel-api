<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\WithdrawTaskCancelAction;
use App\Http\Resources\Api\V1\TaskCancelRequestResource;
use App\Models\Task;
use App\Models\TaskCancelRequest;

final class WithdrawTaskCancelController
{
    public function __construct(private readonly WithdrawTaskCancelAction $action) {}

    public function __invoke(Task $task, TaskCancelRequest $cancelRequest): TaskCancelRequestResource
    {
        $cancelRequest = $this->action
            ->handle($cancelRequest)
            ->load('task');

        return TaskCancelRequestResource::make($cancelRequest);
    }
}
