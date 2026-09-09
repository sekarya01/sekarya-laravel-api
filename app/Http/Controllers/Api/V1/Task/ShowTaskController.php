<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;
use Illuminate\Http\Request;

final class ShowTaskController
{
    public function __invoke(Request $request, Task $task): TaskResource
    {
        return TaskResource::make(
            $task->load(['category', 'poster', 'workers', 'skills', 'payment', 'activities']),
        );
    }
}
