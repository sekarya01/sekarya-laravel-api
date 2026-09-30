<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Actions\Chat\UpdateChatReceiptsAction;
use App\Http\Requests\Api\V1\Chat\UpdateChatReceiptsRequest;
use App\Http\Resources\Api\V1\ChatParticipantResource;
use App\Models\ChatRoom;

final class UpdateChatReceiptsController
{
    public function __construct(private readonly UpdateChatReceiptsAction $action) {}

    public function __invoke(UpdateChatReceiptsRequest $request, ChatRoom $room): ChatParticipantResource
    {
        return ChatParticipantResource::make($this->action->handle(
            $room,
            $request->user(),
            $request->filled('delivered_message_id') ? $request->string('delivered_message_id')->value() : null,
            $request->filled('read_message_id') ? $request->string('read_message_id')->value() : null,
        ));
    }
}
