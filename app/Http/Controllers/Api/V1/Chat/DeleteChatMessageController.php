<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Actions\Chat\DeleteChatMessageAction;
use App\Http\Resources\Api\V1\ChatMessageResource;
use App\Models\ChatMessage;
use Illuminate\Http\Request;

final class DeleteChatMessageController
{
    public function __construct(private readonly DeleteChatMessageAction $action) {}

    public function __invoke(Request $request, ChatMessage $message): ChatMessageResource
    {
        return ChatMessageResource::make($this->action->handle($message, $request->user()));
    }
}
