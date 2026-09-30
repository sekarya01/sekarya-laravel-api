<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Actions\Chat\StoreChatAttachmentAction;
use App\Data\Chat\StoreChatAttachmentData;
use App\Http\Requests\Api\V1\Chat\StoreChatAttachmentRequest;
use App\Http\Resources\Api\V1\ChatAttachmentResource;
use App\Models\ChatRoom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class StoreChatAttachmentController
{
    public function __construct(private readonly StoreChatAttachmentAction $action) {}

    public function __invoke(StoreChatAttachmentRequest $request, ChatRoom $room): JsonResponse
    {
        return ChatAttachmentResource::make(
            $this->action->handle($room, StoreChatAttachmentData::fromRequest($request), $request->user()),
        )->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
