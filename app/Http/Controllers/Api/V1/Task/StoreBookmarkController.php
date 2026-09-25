<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Actions\Task\StoreTaskBookmarkAction;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class StoreBookmarkController
{
    public function __construct(private readonly StoreTaskBookmarkAction $action) {}

    public function __invoke(Request $request, Task $task): Response
    {
        $this->action->handle($task, $request->user());

        return response()->noContent();
    }
}
