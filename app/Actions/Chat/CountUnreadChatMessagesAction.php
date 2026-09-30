<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Enums\ChatRoomStatus;
use App\Models\ChatMessage;
use App\Models\User;

/**
 * Angka badge ikon chat: pesan orang lain yang belum dibaca di seluruh room
 * sendiri. COUNT di server — satu kueri, bukan jumlah `unread_count` halaman
 * yang kebetulan sudah dimuat klien.
 */
final class CountUnreadChatMessagesAction
{
    public function handle(User $user): int
    {
        return ChatMessage::query()
            ->join('chat_participants as p', function ($join) use ($user): void {
                $join->on('p.room_id', '=', 'chat_messages.room_id')
                    ->where('p.user_id', '=', $user->getKey());
            })
            ->join('chat_rooms as r', 'r.id', '=', 'chat_messages.room_id')
            ->where('r.status', '!=', ChatRoomStatus::Deactivated->value)
            ->where('chat_messages.sender_id', '!=', $user->getKey())
            ->whereRaw("chat_messages.id > COALESCE(p.last_read_message_id, '')")
            ->count();
    }
}
