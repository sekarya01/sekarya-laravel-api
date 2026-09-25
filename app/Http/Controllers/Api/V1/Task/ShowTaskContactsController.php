<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\ShowTaskContactsAction;
use App\Http\Resources\Api\V1\TaskContactResource;
use App\Models\Task;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ShowTaskContactsController
{
    public function __construct(private readonly ShowTaskContactsAction $action) {}

    public function __invoke(Task $task): AnonymousResourceCollection
    {
        return TaskContactResource::collection($this->action->handle($task));
    }
}
