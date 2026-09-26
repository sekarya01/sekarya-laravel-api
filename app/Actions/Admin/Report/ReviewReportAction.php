<?php

declare(strict_types=1);

namespace App\Actions\Admin\Report;

use App\Enums\ReportStatus;
use App\Models\Admin;
use App\Models\UserReport;

/** Tandai laporan sudah ditinjau (G7). */
final class ReviewReportAction
{
    public function handle(UserReport $report, Admin $admin): UserReport
    {
        $report->forceFill([
            'status' => ReportStatus::Reviewed,
            'reviewed_by' => $admin->getKey(),
            'reviewed_at' => now(),
        ])->save();

        return $report;
    }
}
