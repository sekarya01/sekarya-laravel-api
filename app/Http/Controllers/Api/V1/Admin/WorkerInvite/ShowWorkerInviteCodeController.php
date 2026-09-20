<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\WorkerInvite;

use App\Http\Resources\Api\V1\Admin\AdminWorkerInviteCodeResource;
use App\Models\WorkerInviteCode;

final class ShowWorkerInviteCodeController
{
    public function __invoke(WorkerInviteCode $code): AdminWorkerInviteCodeResource
    {
        $code->loadCount('redemptions')->load('creator');

        return AdminWorkerInviteCodeResource::make($code);
    }
}
