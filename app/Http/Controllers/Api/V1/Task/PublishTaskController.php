<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\NotifyNearbyWorkersAction;
use App\Actions\Task\PublishTaskAction;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;
use Illuminate\Http\Request;

final class PublishTaskController
{
    public function __construct(
        private readonly PublishTaskAction $action,
        private readonly NotifyNearbyWorkersAction $notify,
    ) {}

    public function __invoke(Request $request, Task $task): TaskResource
    {
        $published = $this->action->handle($task, $request->user());

        // Mitra sekitar dikabari saat tugas benar-benar tayang (B13).
        $notified = $this->notify->handle($published);

        return TaskResource::make($published->load(['category', 'poster', 'skills']))
            ->additional(['meta' => ['notified_workers' => $notified]]);
    }
}
