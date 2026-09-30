<?php

declare(strict_types=1);

namespace App\Data\Chat;

use App\Data\CursorPageData;
use App\Enums\ChatRoomStatus;
use App\Http\Requests\Api\V1\Chat\ListChatRoomsRequest;

final readonly class ListChatRoomsData
{
    public function __construct(
        public CursorPageData $page = new CursorPageData,
        public ?ChatRoomStatus $status = null,
    ) {}

    public static function fromRequest(ListChatRoomsRequest $request): self
    {
        return new self(
            page: $request->page(),
            status: $request->filled('status') ? ChatRoomStatus::from($request->string('status')->value()) : null,
        );
    }
}
