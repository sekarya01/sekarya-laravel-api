<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\ApproveAllActivitiesAction;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Task;
use Illuminate\Http\Request;

/**
 * Setujui semua hasil yang diserahkan pada satu task (B16). Mengembalikan
 * task dengan bentuk yang sama seperti `GET tasks/{task}` supaya klien tidak
 * perlu panggilan kedua untuk menyegarkan layar.
 */
final class ApproveAllActivitiesController
{
    public function __construct(private readonly ApproveAllActivitiesAction $action) {}

    public function __invoke(Request $request, Task $task): TaskResource
    {
        $this->action->handle($task, $request->user());

        // Bentuk detail dari SATU sumber (Task::loadDetailFor) — sama dengan
        // GET tasks/{task}, termasuk badge identitas tanpa N+1.
        return TaskResource::make($task->refresh()->loadDetailFor($request->user()));
    }
}
