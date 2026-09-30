<?php

declare(strict_types=1);

namespace App\Data\Chat;

use App\Data\CursorPageData;
use App\Http\Requests\Api\V1\Chat\ListChatMessagesRequest;

final readonly class ListChatMessagesData
{
    public function __construct(
        public CursorPageData $page = new CursorPageData,
        public ?string $afterId = null,
    ) {}

    public static function fromRequest(ListChatMessagesRequest $request): self
    {
        return new self(
            page: $request->page(),
            afterId: $request->filled('after_id') ? strtoupper($request->string('after_id')->value()) : null,
        );
    }
}
