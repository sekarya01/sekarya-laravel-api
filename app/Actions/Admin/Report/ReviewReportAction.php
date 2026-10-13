<?php

declare(strict_types=1);

namespace App\Actions\Admin\Report;

use App\Enums\AdminAction;
use App\Enums\ReportStatus;
use App\Models\Admin;
use App\Models\UserReport;
use App\Support\AdminAuditRecorder;
use Illuminate\Database\ConnectionInterface;

/**
 * Tandai laporan sudah ditinjau (G7).
 *
 * Tercatat di jejak audit seperti setiap keputusan pengelola lain — tanpa itu
 * tidak ada yang bisa menjawab siapa menutup laporan atas seseorang. Idempoten:
 * laporan yang sudah ditinjau dikembalikan apa adanya, karena menimpa
 * `reviewed_by` diam-diam menghapus jawaban atas pertanyaan yang sama.
 */
final class ReviewReportAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly AdminAuditRecorder $audit,
    ) {}

    /** [#impure] [#io: db] [#tx: own] [#lock: user_reports] */
    public function handle(UserReport $report, Admin $admin): UserReport
    {
        return $this->db->transaction(function () use ($report, $admin): UserReport {
            $report = UserReport::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();

            if ($report->status === ReportStatus::Reviewed) {
                return $report;
            }

            $report->forceFill([
                'status' => ReportStatus::Reviewed,
                'reviewed_by' => $admin->getKey(),
                'reviewed_at' => now(),
            ])->save();

            // Di DALAM transaksi: jejak "pengelola meninjau X" tidak boleh
            // tertinggal bila tinjauannya sendiri batal.
            $this->audit->record($admin, AdminAction::ReportReviewed, (int) $report->getKey());

            return $report;
        });
    }
}
