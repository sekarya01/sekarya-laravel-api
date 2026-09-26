<?php

declare(strict_types=1);

namespace App\Actions\Admin\Report;

use App\Enums\ReportStatus;
use App\Models\UserReport;
use Illuminate\Contracts\Pagination\CursorPaginator;

/** Antrean laporan pengguna (G7) untuk admin. */
final class ListReportsAction
{
    /**
     * @return CursorPaginator<int, UserReport>
     */
    public function handle(?ReportStatus $status, int $perPage = 20): CursorPaginator
    {
        return UserReport::query()
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->with(['reporter', 'reported', 'task'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate($perPage);
    }
}
