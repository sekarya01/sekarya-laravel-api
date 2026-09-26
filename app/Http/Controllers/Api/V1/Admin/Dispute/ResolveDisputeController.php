<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Dispute;

use App\Actions\Admin\Dispute\ResolveDisputeAction;
use App\Enums\DisputeResolution;
use App\Http\Requests\Api\V1\Admin\ResolveDisputeRequest;
use App\Http\Resources\Api\V1\TaskDisputeResource;
use App\Models\TaskDispute;

/** Putuskan sengketa (G5). */
final class ResolveDisputeController
{
    public function __construct(private readonly ResolveDisputeAction $action) {}

    public function __invoke(ResolveDisputeRequest $request, TaskDispute $dispute): TaskDisputeResource
    {
        $resolved = $this->action->handle(
            $dispute,
            $request->user(),
            DisputeResolution::from($request->string('resolution')->value()),
            $request->filled('note') ? $request->string('note')->value() : null,
        );

        return TaskDisputeResource::make($resolved->load(['task', 'raiser']));
    }
}
