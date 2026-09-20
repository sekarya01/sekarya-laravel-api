<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\WorkerInvite;

use App\Http\Resources\Api\V1\Admin\AdminWorkerInviteCodeResource;
use App\Models\WorkerInviteCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListWorkerInviteCodesController
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $codes = WorkerInviteCode::query()
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return AdminWorkerInviteCodeResource::collection($codes);
    }
}
