<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Dispute;

use App\Actions\Admin\Dispute\ListDisputesAction;
use App\Http\Requests\Api\V1\Admin\ListDisputesRequest;
use App\Http\Resources\Api\V1\TaskDisputeResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Antrean sengketa (G5). */
final class ListDisputesController
{
    public function __construct(private readonly ListDisputesAction $action) {}

    public function __invoke(ListDisputesRequest $request): AnonymousResourceCollection
    {
        return TaskDisputeResource::collection($this->action->handle($request->status()));
    }
}
