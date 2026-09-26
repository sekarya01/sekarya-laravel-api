<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Models\Task;
use App\Models\TaskBookmark;
use App\Models\User;

/** Lepas simpanan (B11). Idempoten: tidak ada = bukan galat. */
final class DestroyTaskBookmarkAction
{
    public function handle(Task $task, User $user): void
    {
        TaskBookmark::query()
            ->where('user_id', $user->getKey())
            ->where('task_id', $task->getKey())
            ->delete();
    }
}
