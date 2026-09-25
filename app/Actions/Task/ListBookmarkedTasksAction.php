<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Data\Task\ListTasksData;
use App\Models\Task;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Tugas yang disimpan seseorang (B11), terbaru dulu.
 *
 * Berangkat dari `tasks` yang punya baris bookmark milik orang ini — subquery
 * pada indeks `task_bookmarks(user_id, task_id)` ciptaan `unique`, bukan
 * pemindaian tabel. Selalu milik pemanggil; tidak ada parameter pemilik.
 */
final class ListBookmarkedTasksAction
{
    /** @return CursorPaginator<int, Task> */
    public function handle(ListTasksData $data, User $user): CursorPaginator
    {
        return Task::query()
            ->select('tasks.*')
            ->with(['category', 'poster', 'skills'])
            ->whereExists(fn ($q) => $q
                ->selectRaw('1')
                ->from('task_bookmarks')
                ->whereColumn('task_bookmarks.task_id', 'tasks.id')
                ->where('task_bookmarks.user_id', $user->getKey()))
            ->latestFirst()
            ->cursorPaginate($data->page->perPage);
    }
}
