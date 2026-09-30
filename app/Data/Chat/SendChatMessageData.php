<?php

declare(strict_types=1);

namespace App\Data\Chat;

use App\Enums\ChatMessageType;
use App\Http\Requests\Api\V1\Chat\SendChatMessageRequest;

final readonly class SendChatMessageData
{
    public function __construct(
        public ChatMessageType $type,
        public ?string $caption = null,
        public ?string $attachmentId = null,
        public ?ChatMessageType $replyType = null,
        public ?string $repliedMessageId = null,
        public ?string $clientMessageId = null,
    ) {}

    public static function fromRequest(SendChatMessageRequest $request): self
    {
        $caption = $request->filled('caption') ? trim($request->string('caption')->value()) : null;

        return new self(
            type: ChatMessageType::from($request->string('type')->value()),
            caption: $caption === '' ? null : $caption,
            attachmentId: $request->filled('attachment_id') ? strtoupper($request->string('attachment_id')->value()) : null,
            replyType: $request->filled('reply_type') ? ChatMessageType::from($request->string('reply_type')->value()) : null,
            repliedMessageId: $request->filled('replied_message_id') ? strtoupper($request->string('replied_message_id')->value()) : null,
            clientMessageId: $request->filled('client_message_id') ? $request->string('client_message_id')->value() : null,
        );
    }
}
