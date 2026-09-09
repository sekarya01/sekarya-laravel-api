<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\PublishTaskAction;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;
use Illuminate\Http\Request;

final class PublishTaskController
{
    public function __construct(private readonly PublishTaskAction $action) {}

    public function __invoke(Request $request, Task $task): TaskResource
    {
        return TaskResource::make(
            $this->action->handle($task, $request->user())->load(['category', 'poster', 'skills']),
        );
    }
}
