<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Data\Chat\SendChatMessageData;
use App\Enums\ChatMessageType;
use App\Exceptions\Domain\ChatAttachmentInvalidException;
use App\Exceptions\Domain\ChatRepliedMessageInvalidException;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use App\Support\Chat\ChatAccess;
use App\Support\Chat\ChatNotifier;
use Illuminate\Database\ConnectionInterface;

/**
 * Kirim satu pesan.
 *
 * Idempoten per `(room, pengirim, client_message_id)`: kirim ulang dari
 * klien (jaringan putus sebelum balasan diterima) mengembalikan pesan yang
 * SAMA — `wasRecentlyCreated` false, controller menjawab 200 alih-alih 201.
 *
 * Pesan sendiri langsung menjadi penanda baca pengirimnya: ia sudah
 * "membaca" semua sampai pesannya sendiri.
 */
final class SendChatMessageAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly ChatAccess $access,
        private readonly ChatNotifier $notifier,
    ) {}

    public function handle(ChatRoom $room, SendChatMessageData $data, User $user): ChatMessage
    {
        $message = $this->db->transaction(function () use ($room, $data, $user): ChatMessage {
            $locked = ChatRoom::query()->whereKey($room->getKey())->lockForUpdate()->firstOrFail();
            $sender = $this->access->writer($locked, $user);

            if ($data->clientMessageId !== null) {
                $existing = ChatMessage::query()->withTrashed()
                    ->where('room_id', $locked->getKey())
                    ->where('sender_id', $user->getKey())
                    ->where('client_message_id', $data->clientMessageId)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $body = $data->type === ChatMessageType::Reply ? $data->replyType : $data->type;
            $attachment = $this->attachmentFor($locked, $body, $data->attachmentId, $user);
            $replied = $data->type === ChatMessageType::Reply
                ? $this->repliedIn($locked, (string) $data->repliedMessageId)
                : null;

            $message = ChatMessage::query()->create([
                'room_id' => $locked->getKey(),
                'sender_id' => $user->getKey(),
                'client_message_id' => $data->clientMessageId,
                'type' => $data->type,
                'reply_type' => $data->type === ChatMessageType::Reply ? $body : null,
                'replied_message_id' => $replied?->getKey(),
                'attachment_id' => $attachment?->getKey(),
                'caption' => $body->allowsCaption() ? $data->caption : null,
            ]);

            $locked->forceFill(['last_message_id' => $message->getKey()])->save();
            $sender->forceFill([
                'last_read_message_id' => $message->getKey(),
                'last_delivered_message_id' => $message->getKey(),
                'last_read_at' => now(),
            ])->save();

            $this->notifier->messageSent($locked, $message, $sender);

            return $message;
        });

        $message->load(['attachment', 'replied.attachment']);
        $message->setRelation('room', $room->load('participants.user'));

        return $message;
    }

    private function attachmentFor(ChatRoom $room, ChatMessageType $body, ?string $id, User $user): ?ChatAttachment
    {
        if (! $body->isMedia()) {
            if ($id !== null) {
                throw ChatAttachmentInvalidException::because('text_has_no_attachment');
            }

            return null;
        }

        $attachment = $id === null ? null : ChatAttachment::query()
            ->whereKey($id)
            ->where('room_id', $room->getKey())
            ->where('uploader_id', $user->getKey())
            ->lockForUpdate()
            ->first();

        if ($attachment === null) {
            throw ChatAttachmentInvalidException::because('not_found');
        }

        if ($attachment->kind !== $body) {
            throw ChatAttachmentInvalidException::because('kind_mismatch');
        }

        if (ChatMessage::query()->withTrashed()->where('attachment_id', $attachment->getKey())->exists()) {
            throw ChatAttachmentInvalidException::because('already_used');
        }

        return $attachment;
    }

    private function repliedIn(ChatRoom $room, string $id): ChatMessage
    {
        $replied = ChatMessage::query()
            ->whereKey($id)
            ->where('room_id', $room->getKey())
            ->where('type', '!=', ChatMessageType::System->value)
            ->first();

        return $replied ?? throw ChatRepliedMessageInvalidException::make();
    }
}
