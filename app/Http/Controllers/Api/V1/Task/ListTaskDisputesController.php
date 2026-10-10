<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\ListTaskDisputesAction;
use App\Http\Resources\Api\V1\TaskDisputeResource;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Sengketa task ini — pemberi kerja semua, mitra miliknya sendiri. */
final class ListTaskDisputesController
{
    public function __construct(private readonly ListTaskDisputesAction $action) {}

    public function __invoke(Request $request, Task $task): AnonymousResourceCollection
    {
        return TaskDisputeResource::collection($this->action->handle($task, $request->user()));
    }
}
