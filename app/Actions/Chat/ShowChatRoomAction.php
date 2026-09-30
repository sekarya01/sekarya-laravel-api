<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Models\ChatRoom;
use App\Models\User;
use App\Support\Chat\ChatAccess;

/** Satu room. Bukan peserta = 404; sudah dinonaktifkan = 410. */
final class ShowChatRoomAction
{
    public function __construct(private readonly ChatAccess $access) {}

    public function handle(ChatRoom $room, User $user): ChatRoom
    {
        $this->access->participant($room, $user);

        return $room->freshFor($user);
    }
}
