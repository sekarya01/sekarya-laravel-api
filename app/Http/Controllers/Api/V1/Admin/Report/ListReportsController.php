<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Report;

use App\Actions\Admin\Report\ListReportsAction;
use App\Http\Requests\Api\V1\Admin\ListReportsRequest;
use App\Http\Resources\Api\V1\UserReportResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Antrean laporan pengguna (G7). */
final class ListReportsController
{
    public function __construct(private readonly ListReportsAction $action) {}

    public function __invoke(ListReportsRequest $request): AnonymousResourceCollection
    {
        return UserReportResource::collection($this->action->handle($request->status()));
    }
}
