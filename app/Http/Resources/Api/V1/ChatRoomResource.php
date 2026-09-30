<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ChatParticipant;
use App\Models\ChatRoom;
use App\Models\User;
use App\Support\Chat\ChatPreview;
use Illuminate\Http\Request;

/**
 * Satu room untuk penontonnya (`$request->user()`): `unread_count`,
 * `is_muted`, dan `permissions` milik penonton itu.
 *
 * Nama & foto room dibaca dari task-nya (tidak disalin).
 *
 * @mixin ChatRoom
 */
final class ChatRoomResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $me = $this->participants->first(
            fn (ChatParticipant $p): bool => $viewer instanceof User && $p->user_id === $viewer->getKey(),
        );
        $canWrite = $this->status->acceptsMessages() && $me?->isActive() === true;
        $photo = $this->task->photos[0] ?? null;

        return [
            'id' => $this->ulid,
            'task_id' => $this->task->ulid,
            'room_name' => $this->task->title,
            'room_avatar' => is_string($photo) ? $this->publicUrl($photo) : null,
            'room_type' => $this->type->value,
            'room_status' => $this->status->value,
            'participants' => ChatParticipantResource::collection($this->participants),
            'participants_count' => $this->participants->filter->isActive()->count(),
            'last_message' => $this->lastMessage(),
            'unread_count' => (int) ($this->unread_count ?? 0),
            'permissions' => [
                'can_send' => $canWrite,
                'can_attach' => $canWrite,
                'can_reply' => $canWrite,
            ],
            'is_muted' => $me?->muted_at !== null,
            'expired_at' => $this->iso($this->expired_at),
            'deactivated_at' => $this->iso($this->deactivated_at),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
            'deleted_at' => $this->iso($this->deleted_at),
        ];
    }

    /** @return array<string, mixed>|null */
    private function lastMessage(): ?array
    {
        $message = $this->lastMessage;
        if ($message === null) {
            return null;
        }

        $sender = $this->participants->firstWhere('user_id', $message->sender_id);

        return [
            'id' => $message->id,
            'sender_id' => $sender?->user->ulid,
            'content' => [
                'type' => $message->type->value,
                'reply_type' => $message->reply_type?->value,
            ],
            'preview' => ChatPreview::of($message),
            'caption' => $message->trashed() ? null : $message->caption,
            'created_at' => $this->iso($message->created_at),
            'deleted_at' => $this->iso($message->deleted_at),
        ];
    }
}
