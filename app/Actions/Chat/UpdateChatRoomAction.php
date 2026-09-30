<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Models\ChatRoom;
use App\Models\User;
use App\Support\Chat\ChatAccess;

/**
 * Pengaturan room milik sendiri — sekarang hanya bisukan notifikasi.
 * Pesan tetap sampai sebagai sinyal senyap (daftar tetap sinkron).
 */
final class UpdateChatRoomAction
{
    public function __construct(private readonly ChatAccess $access) {}

    public function handle(ChatRoom $room, User $user, bool $muted): ChatRoom
    {
        $participant = $this->access->participant($room, $user);

        if ($muted !== ($participant->muted_at !== null)) {
            $participant->forceFill(['muted_at' => $muted ? now() : null])->save();
        }

        return $room->freshFor($user);
    }
}
