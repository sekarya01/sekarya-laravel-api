<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\WorkerInvite;

use App\Actions\Admin\WorkerInvite\ListWorkerInvitesAction;
use App\Http\Requests\Api\V1\Admin\WorkerInvitePageRequest;
use App\Http\Resources\Api\V1\Admin\AdminWorkerInviteRedemptionResource;
use App\Models\WorkerInviteCode;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListWorkerInviteRedemptionsController
{
    public function __construct(private readonly ListWorkerInvitesAction $action) {}

    public function __invoke(WorkerInvitePageRequest $request, WorkerInviteCode $code): AnonymousResourceCollection
    {
        return AdminWorkerInviteRedemptionResource::collection($this->action->redemptions($code, $request->page()));
    }
}
