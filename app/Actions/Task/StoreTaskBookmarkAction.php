<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Models\Task;
use App\Models\TaskBookmark;
use App\Models\User;

/**
 * Simpan tugas ke daftar "disimpan" (B11). Idempoten: menyimpan dua kali
 * tidak menambah baris kedua — `unique(user_id, task_id)` yang menjaminnya.
 */
final class StoreTaskBookmarkAction
{
    public function handle(Task $task, User $user): void
    {
        TaskBookmark::query()->firstOrCreate([
            'user_id' => $user->getKey(),
            'task_id' => $task->getKey(),
        ]);
    }
}
