<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Enums\ChatMessageStatus;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Satu pesan, bentuk tetap untuk semua jenis: field yang tidak berlaku
 * bernilai `null` (string/objek) atau `0` (angka) — tidak pernah hilang.
 *
 * Membaca `room.participants.user` yang dipasang Action (pengirim, status
 * baca, nama pengirim kutipan) — tanpa kueri per baris.
 *
 * @mixin ChatMessage
 */
final class ChatMessageResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Collection<int, ChatParticipant> $participants */
        $participants = $this->room->participants->keyBy('user_id');
        $deleted = $this->resource->trashed();
        $attachment = $deleted ? null : $this->attachment;

        return [
            'id' => $this->id,
            'room_id' => $this->room->ulid,
            'client_message_id' => $this->client_message_id,
            'sender_id' => $participants->get($this->sender_id)?->user->ulid,
            'content' => [
                'id' => $attachment?->id,
                'type' => $this->type->value,
                'reply_type' => $this->reply_type?->value,
                ...$this->media($attachment),
                'waveform' => $attachment?->waveform ?? [],
                'replied' => $deleted ? null : $this->replied($participants),
                'system_event' => $this->system_event?->value,
                'system_params' => (object) ($this->system_params ?? []),
            ],
            'caption' => $deleted ? null : $this->caption,
            'status' => $this->status($participants)->value,
            'is_edited' => false,
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
            'deleted_at' => $this->iso($this->deleted_at),
        ];
    }

    /** @return array<string, mixed> */
    private function media(?ChatAttachment $attachment): array
    {
        return [
            'reference' => $this->publicUrl($attachment?->path),
            'file_name' => $attachment?->file_name,
            'extension' => $attachment?->extension,
            'mime_type' => $attachment?->mime_type,
            'duration' => $attachment->duration ?? 0,
            'size' => $attachment->size ?? 0,
            'width' => $attachment->width ?? 0,
            'height' => $attachment->height ?? 0,
            // Foto = berkasnya sendiri; video = bingkai awal yang diunggah
            // perangkat (null untuk video lama); jenis lain null.
            'thumbnail' => $this->publicUrl($attachment?->thumbnailPath()),
        ];
    }

    /**
     * Ringkasan pesan yang dibalas. Balasan tidak bersarang.
     *
     * @param  Collection<int, ChatParticipant>  $participants
     * @return array<string, mixed>|null
     */
    private function replied(Collection $participants): ?array
    {
        $replied = $this->replied;
        if ($replied === null) {
            return null;
        }

        $gone = $replied->trashed();
        $attachment = $gone ? null : $replied->attachment;
        $sender = $participants->get($replied->sender_id);
        $caption = $gone ? null : $replied->caption;

        return [
            'id' => $replied->id,
            'sender_id' => $sender?->user->ulid,
            'sender_name' => $sender?->displayName(),
            'content' => [
                'type' => $replied->type->value,
                'reply_type' => $replied->reply_type?->value,
                'reference' => $this->publicUrl($attachment?->path),
                'thumbnail' => $this->publicUrl($attachment?->thumbnailPath()),
                'extension' => $attachment?->extension,
                'file_name' => $attachment?->file_name,
                'duration' => $attachment->duration ?? 0,
            ],
            'caption' => $caption === null ? null : mb_strimwidth($caption, 0, 200, '…'),
            'is_deleted' => $gone,
        ];
    }

    /**
     * Status dari sudut pengirim: `read` bila SEMUA peserta aktif lain sudah
     * membaca sampai pesan ini, `delivered` bila semuanya sudah menerima,
     * selain itu `sent`. ULID urut waktu → perbandingan string cukup.
     *
     * @param  Collection<int, ChatParticipant>  $participants
     */
    private function status(Collection $participants): ChatMessageStatus
    {
        $others = $participants->filter(
            fn (ChatParticipant $p): bool => $p->user_id !== $this->sender_id && $p->isActive(),
        );

        if ($this->sender_id === null || $others->isEmpty()) {
            return ChatMessageStatus::Sent;
        }

        $reached = fn (?string $mark): bool => $mark !== null && strcmp($mark, $this->id) >= 0;

        if ($others->every(fn (ChatParticipant $p): bool => $reached($p->last_read_message_id))) {
            return ChatMessageStatus::Read;
        }

        if ($others->every(fn (ChatParticipant $p): bool => $reached($p->last_delivered_message_id))) {
            return ChatMessageStatus::Delivered;
        }

        return ChatMessageStatus::Sent;
    }
}
