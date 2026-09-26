<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Data\User\ReportUserData;
use App\Enums\ReportStatus;
use App\Exceptions\Domain\CannotReportSelfException;
use App\Models\Task;
use App\Models\User;
use App\Models\UserReport;

/**
 * Laporkan pengguna lain (G7). Laporan masuk antrean admin; tidak ada efek
 * otomatis pada yang dilaporkan — keputusan menyusul dari pengelola.
 */
final class ReportUserAction
{
    public function handle(ReportUserData $data, User $reporter, User $reported): UserReport
    {
        if ($reporter->is($reported)) {
            throw CannotReportSelfException::make();
        }

        $taskId = $data->taskUlid === null
            ? null
            : Task::query()->where('ulid', $data->taskUlid)->value('id');

        return UserReport::query()->create([
            'reporter_id' => $reporter->getKey(),
            'reported_id' => $reported->getKey(),
            'task_id' => $taskId,
            'reason' => $data->reason,
            'note' => $data->note,
            'status' => ReportStatus::Open,
        ]);
    }
}
