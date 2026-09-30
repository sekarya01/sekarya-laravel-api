<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Actions\Chat\ListChatRoomsAction;
use App\Data\Chat\ListChatRoomsData;
use App\Http\Requests\Api\V1\Chat\ListChatRoomsRequest;
use App\Http\Resources\Api\V1\ChatRoomResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListChatRoomsController
{
    public function __construct(private readonly ListChatRoomsAction $action) {}

    public function __invoke(ListChatRoomsRequest $request): AnonymousResourceCollection
    {
        return ChatRoomResource::collection(
            $this->action->handle(ListChatRoomsData::fromRequest($request), $request->user()),
        );
    }
}
