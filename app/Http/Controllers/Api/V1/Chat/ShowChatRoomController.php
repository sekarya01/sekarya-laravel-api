<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Actions\Chat\ShowChatRoomAction;
use App\Http\Resources\Api\V1\ChatRoomResource;
use App\Models\ChatRoom;
use Illuminate\Http\Request;

final class ShowChatRoomController
{
    public function __construct(private readonly ShowChatRoomAction $action) {}

    public function __invoke(Request $request, ChatRoom $room): ChatRoomResource
    {
        return ChatRoomResource::make($this->action->handle($room, $request->user()));
    }
}
