<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Report;

use App\Actions\Admin\Report\ReviewReportAction;
use App\Http\Resources\Api\V1\UserReportResource;
use App\Models\UserReport;
use Illuminate\Http\Request;

/** Tandai laporan sudah ditinjau (G7). */
final class ReviewReportController
{
    public function __construct(private readonly ReviewReportAction $action) {}

    public function __invoke(Request $request, UserReport $report): UserReportResource
    {
        return UserReportResource::make(
            $this->action->handle($report, $request->user())->load(['reporter', 'reported', 'task']),
        );
    }
}
