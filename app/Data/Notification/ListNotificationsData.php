<?php

declare(strict_types=1);

namespace App\Data\Notification;

use App\Data\CursorPageData;
use App\Http\Requests\Api\V1\Notification\ListNotificationsRequest;

final readonly class ListNotificationsData
{
    public function __construct(
        public CursorPageData $page = new CursorPageData,
        public bool $unreadOnly = false,
    ) {}

    public static function fromRequest(ListNotificationsRequest $request): self
    {
        return new self(
            page: $request->page(),
            unreadOnly: $request->boolean('unread'),
        );
    }
}
