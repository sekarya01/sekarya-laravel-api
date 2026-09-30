<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Chat;

use App\Actions\Admin\Chat\DeactivateChatRoomAction;
use App\Http\Requests\Api\V1\Admin\DeactivateChatRoomRequest;
use App\Http\Resources\Api\V1\AdminChatRoomResource;
use App\Models\Task;

/**
 * Nonaktifkan chat sebuah task (moderasi). Dicari lewat TASK: itu id yang
 * dipegang pengelola dari laporan & sengketa.
 */
final class DeactivateChatRoomController
{
    public function __construct(private readonly DeactivateChatRoomAction $action) {}

    public function __invoke(DeactivateChatRoomRequest $request, Task $task): AdminChatRoomResource
    {
        return AdminChatRoomResource::make($this->action->handle(
            $task->chatRoom()->firstOrFail(),
            $request->user(),
            $request->string('reason')->value(),
        ));
    }
}
