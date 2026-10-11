<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\WorkerInvite;

use App\Actions\Admin\WorkerInvite\ListWorkerInvitesAction;
use App\Http\Requests\Api\V1\Admin\WorkerInvitePageRequest;
use App\Http\Resources\Api\V1\Admin\AdminWorkerInviteCodeResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListWorkerInviteCodesController
{
    public function __construct(private readonly ListWorkerInvitesAction $action) {}

    public function __invoke(WorkerInvitePageRequest $request): AnonymousResourceCollection
    {
        return AdminWorkerInviteCodeResource::collection($this->action->codes($request->page()));
    }
}
