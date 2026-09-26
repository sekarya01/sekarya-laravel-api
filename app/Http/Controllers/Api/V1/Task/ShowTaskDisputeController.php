<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Http\Resources\Api\V1\TaskDisputeResource;
use App\Models\Task;
use App\Models\TaskDispute;
use Illuminate\Http\Request;

/** Tiket kendala task ini (G5). 404 bila belum ada. */
final class ShowTaskDisputeController
{
    public function __invoke(Request $request, Task $task): TaskDisputeResource
    {
        $userId = (int) $request->user()->getKey();
        $isParticipant = (int) $task->poster_id === $userId
            || $task->bids()->where('bidder_id', $userId)->where('status', 'accepted')->exists();

        abort_unless($isParticipant, 403);

        $dispute = TaskDispute::query()
            ->where('task_id', $task->getKey())
            ->orderByDesc('created_at')
            ->first();

        abort_if($dispute === null, 404);

        return TaskDisputeResource::make($dispute);
    }
}
