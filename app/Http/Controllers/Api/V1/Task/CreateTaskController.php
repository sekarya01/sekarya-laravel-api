<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\CreateTaskAction;
use App\Actions\Task\NotifyNearbyWorkersAction;
use App\Data\Task\CreateTaskData;
use App\Http\Requests\Api\V1\Task\CreateTaskRequest;
use App\Http\Resources\Api\V1\TaskResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CreateTaskController
{
    public function __construct(
        private readonly CreateTaskAction $action,
        private readonly NotifyNearbyWorkersAction $notify,
    ) {}

    public function __invoke(CreateTaskRequest $request): JsonResponse
    {
        $task = $this->action->handle(CreateTaskData::fromRequest($request), $request->user());

        // Tugas yang langsung tayang mengabari mitra sekitar (B13). Draf tidak.
        $notified = $request->boolean('publish_now') ? $this->notify->handle($task) : 0;

        return TaskResource::make($task->load(['category', 'poster', 'skills']))
            ->additional(['meta' => ['notified_workers' => $notified]])
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
