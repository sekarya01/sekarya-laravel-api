<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\StartWithCurrentWorkersAction;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Mulai dengan pekerja yang sudah diterima, tanpa menunggu slot penuh.
 *
 * Untuk task yang membutuhkan banyak orang tapi tidak mendapat pelamar
 * sebanyak itu: pekerjaan bertanggal tidak boleh tersandera oleh angka target.
 */
final class StartTaskController
{
    public function __construct(private readonly StartWithCurrentWorkersAction $action) {}

    public function __invoke(Request $request, Task $task): JsonResponse
    {
        $task = $this->action->handle($task, $request->user());

        return TaskResource::make($task->load(['category', 'poster', 'workers', 'skills']))
            ->response();
    }
}
