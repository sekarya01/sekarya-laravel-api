<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ChatRoom;
use Illuminate\Http\Request;

/**
 * Room di sisi pengelola — status saja. Isi percakapan tidak pernah keluar
 * lewat API pengelola.
 *
 * @mixin ChatRoom
 */
final class AdminChatRoomResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'task_id' => $this->task->ulid,
            'room_type' => $this->type->value,
            'room_status' => $this->status->value,
            'expired_at' => $this->iso($this->expired_at),
            'deactivated_at' => $this->iso($this->deactivated_at),
        ];
    }
}
