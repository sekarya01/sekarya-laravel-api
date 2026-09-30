<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Actions\Chat\ShowTaskChatRoomAction;
use App\Http\Resources\Api\V1\ChatRoomResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowTaskChatRoomController
{
    public function __construct(private readonly ShowTaskChatRoomAction $action) {}

    /** Tidak ada chat untuk pemanggil = `{"data": null}` 200, bukan 404. */
    public function __invoke(Request $request, Task $task): JsonResponse|ChatRoomResource
    {
        $room = $this->action->handle($task, $request->user());

        return $room === null ? new JsonResponse(['data' => null]) : ChatRoomResource::make($room);
    }
}
