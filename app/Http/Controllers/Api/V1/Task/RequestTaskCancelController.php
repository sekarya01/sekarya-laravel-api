<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\RequestTaskCancelAction;
use App\Http\Requests\Api\V1\Task\RequestTaskCancelRequest;
use App\Http\Resources\Api\V1\TaskCancelRequestResource;
use App\Models\Task;

final class RequestTaskCancelController
{
    public function __construct(private readonly RequestTaskCancelAction $action) {}

    public function __invoke(RequestTaskCancelRequest $request, Task $task): TaskCancelRequestResource
    {
        $cancelRequest = $this->action
            ->handle($task, $request->user(), $request->input('reason'))
            ->load(['task', 'approvals']);

        return TaskCancelRequestResource::make($cancelRequest);
    }
}
