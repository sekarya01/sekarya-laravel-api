<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\CountTasksByStatusAction;
use App\Http\Resources\Api\V1\TaskStatusCountsResource;
use Illuminate\Http\Request;

final class ShowPostedTaskCountsController
{
    public function __construct(private readonly CountTasksByStatusAction $action) {}

    public function __invoke(Request $request): TaskStatusCountsResource
    {
        return TaskStatusCountsResource::make($this->action->postedBy($request->user()));
    }
}
