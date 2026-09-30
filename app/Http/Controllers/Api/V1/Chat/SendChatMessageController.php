<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Actions\Chat\SendChatMessageAction;
use App\Data\Chat\SendChatMessageData;
use App\Http\Requests\Api\V1\Chat\SendChatMessageRequest;
use App\Http\Resources\Api\V1\ChatMessageResource;
use App\Models\ChatRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class SendChatMessageController
{
    public function __construct(private readonly SendChatMessageAction $action) {}

    /** 201 pesan baru; 200 bila `client_message_id` yang sama sudah diterima. */
    public function __invoke(SendChatMessageRequest $request, ChatRoom $room): JsonResponse
    {
        $message = $this->action->handle($room, SendChatMessageData::fromRequest($request), $request->user());

        return ChatMessageResource::make($message)->response()->setStatusCode(
            $message->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK,
        );
    }
}
