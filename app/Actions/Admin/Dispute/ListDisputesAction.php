<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dispute;

use App\Enums\DisputeStatus;
use App\Models\TaskDispute;
use Illuminate\Contracts\Pagination\CursorPaginator;

/** Antrean sengketa (G5). */
final class ListDisputesAction
{
    /**
     * @return CursorPaginator<int, TaskDispute>
     */
    public function handle(?DisputeStatus $status, int $perPage = 20): CursorPaginator
    {
        return TaskDispute::query()
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->with(['task', 'raiser'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }
}
