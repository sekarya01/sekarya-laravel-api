<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Models\Task;
use App\Models\TaskDispute;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Sengketa sebuah task untuk satu peserta. Pemberi kerja melihat semuanya;
 * mitra hanya sengketa atas hasil kerjanya SENDIRI — alasan & foto bukti
 * tentang mitra lain bukan urusannya.
 *
 * Tanpa cursor: sengketa per task dibatasi jumlah mitranya (satu terbuka per
 * mitra), dan klien membutuhkan seluruhnya untuk ditempel ke kartu mitra.
 */
final class ListTaskDisputesAction
{
    /** @return Collection<int, TaskDispute> */
    public function handle(Task $task, User $viewer): Collection
    {
        return TaskDispute::query()
            ->where('task_id', $task->getKey())
            ->when(
                (int) $task->poster_id !== (int) $viewer->getKey(),
                fn ($q) => $q->whereHas('activity', fn ($a) => $a->where('worker_id', $viewer->getKey())),
            )
            ->with('activity')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }
}
