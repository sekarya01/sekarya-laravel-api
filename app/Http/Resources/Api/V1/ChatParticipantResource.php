<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ChatParticipant;
use Illuminate\Http\Request;

/**
 * Peserta room — data TAMPILAN saja. Tidak ada email/telepon/alamat: kontak
 * langsung sengaja tidak dibuka (keputusan B17).
 *
 * @mixin ChatParticipant
 */
final class ChatParticipantResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->user->ulid,
            'type' => $this->type->value,
            'name' => $this->resource->displayName(),
            'avatar' => $this->publicUrl($this->resource->avatarPath()),
            'role' => $this->type->role(),
            'joined_at' => $this->iso($this->joined_at),
            'left_at' => $this->iso($this->left_at),
            'last_delivered_message_id' => $this->last_delivered_message_id,
            'last_read_message_id' => $this->last_read_message_id,
            'last_read_at' => $this->iso($this->last_read_at),
        ];
    }
}
