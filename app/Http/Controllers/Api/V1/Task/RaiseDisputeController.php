<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\RaiseDisputeAction;
use App\Data\Task\RaiseDisputeData;
use App\Http\Requests\Api\V1\Task\RaiseDisputeRequest;
use App\Http\Resources\Api\V1\TaskDisputeResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/** Ajukan kendala (G5). */
final class RaiseDisputeController
{
    public function __construct(private readonly RaiseDisputeAction $action) {}

    public function __invoke(RaiseDisputeRequest $request, Task $task): JsonResponse
    {
        $dispute = $this->action->handle(RaiseDisputeData::fromRequest($request), $task, $request->user());

        return TaskDisputeResource::make($dispute)->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
