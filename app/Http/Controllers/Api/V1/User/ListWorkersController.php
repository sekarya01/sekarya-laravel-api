<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\ListWorkersAction;
use App\Data\User\ListWorkersData;
use App\Http\Requests\Api\V1\User\ListWorkersRequest;
use App\Http\Resources\Api\V1\WorkerResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListWorkersController
{
    public function __construct(private readonly ListWorkersAction $action) {}

    public function __invoke(ListWorkersRequest $request): AnonymousResourceCollection
    {
        return WorkerResource::collection(
            $this->action->handle(ListWorkersData::fromRequest($request)),
        );
    }
}
