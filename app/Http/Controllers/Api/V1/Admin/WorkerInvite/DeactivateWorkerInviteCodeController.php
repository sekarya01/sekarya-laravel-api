<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\WorkerInvite;

use App\Actions\Admin\WorkerInvite\DeactivateWorkerInviteCodeAction;
use App\Http\Resources\Api\V1\Admin\AdminWorkerInviteCodeResource;
use App\Models\WorkerInviteCode;
use Illuminate\Http\Request;

final class DeactivateWorkerInviteCodeController
{
    public function __construct(private readonly DeactivateWorkerInviteCodeAction $action) {}

    public function __invoke(Request $request, WorkerInviteCode $code): AdminWorkerInviteCodeResource
    {
        $code = $this->action->handle($code, $request->user(), $request->ip());

        return AdminWorkerInviteCodeResource::make($code->loadCount('redemptions'));
    }
}
