<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use App\Data\CursorPageData;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Contracts\Pagination\CursorPaginator;

final class ListActivitiesAction
{
    /** @return CursorPaginator<int, Activity> */
    public function forWorker(User $worker, CursorPageData $page): CursorPaginator
    {
        return Activity::query()
            ->where('worker_id', $worker->getKey())
            // worker wajib: mobile memakai worker.name sebagai syarat
            // tampil stepper status pengerjaan di detail.
            ->with(['worker', 'task.category', 'task.poster', 'payment'])
            ->latestFirst()
            ->cursorPaginate($page->perPage);
    }
}
