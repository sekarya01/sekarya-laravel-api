<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Actions\Chat\UpdateChatRoomAction;
use App\Http\Requests\Api\V1\Chat\UpdateChatRoomRequest;
use App\Http\Resources\Api\V1\ChatRoomResource;
use App\Models\ChatRoom;

final class UpdateChatRoomController
{
    public function __construct(private readonly UpdateChatRoomAction $action) {}

    public function __invoke(UpdateChatRoomRequest $request, ChatRoom $room): ChatRoomResource
    {
        return ChatRoomResource::make(
            $this->action->handle($room, $request->user(), $request->boolean('is_muted')),
        );
    }
}
