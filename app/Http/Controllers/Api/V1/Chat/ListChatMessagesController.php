<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Actions\Chat\ListChatMessagesAction;
use App\Data\Chat\ListChatMessagesData;
use App\Http\Requests\Api\V1\Chat\ListChatMessagesRequest;
use App\Http\Resources\Api\V1\ChatMessageResource;
use App\Models\ChatRoom;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListChatMessagesController
{
    public function __construct(private readonly ListChatMessagesAction $action) {}

    public function __invoke(ListChatMessagesRequest $request, ChatRoom $room): AnonymousResourceCollection
    {
        return ChatMessageResource::collection(
            $this->action->handle($room, ListChatMessagesData::fromRequest($request), $request->user()),
        );
    }
}
