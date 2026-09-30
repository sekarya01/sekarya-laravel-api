<?php

declare(strict_types=1);

namespace App\Support\Chat;

use App\Enums\ChatRoomStatus;
use App\Exceptions\Domain\ChatRoomDeactivatedException;
use App\Exceptions\Domain\ChatRoomExpiredException;
use App\Exceptions\Domain\ChatRoomNotFoundException;
use App\Models\ChatParticipant;
use App\Models\ChatRoom;
use App\Models\User;

/**
 * Gerbang tunggal seluruh aksi chat. Urutannya disengaja: keanggotaan dulu
 * (bukan peserta = 404, room orang lain tidak dikonfirmasi ada), baru status
 * room (410 hanya untuk pesertanya).
 */
final class ChatAccess
{
    public function participant(ChatRoom $room, User $user): ChatParticipant
    {
        $participant = ChatParticipant::query()
            ->where('room_id', $room->getKey())
            ->where('user_id', $user->getKey())
            ->first();

        if ($participant === null) {
            throw ChatRoomNotFoundException::make();
        }

        if ($room->status === ChatRoomStatus::Deactivated) {
            throw ChatRoomDeactivatedException::make();
        }

        return $participant;
    }

    /** Peserta yang boleh MENULIS: room aktif dan ia belum keluar. */
    public function writer(ChatRoom $room, User $user): ChatParticipant
    {
        $participant = $this->participant($room, $user);

        if (! $room->status->acceptsMessages() || ! $participant->isActive()) {
            throw ChatRoomExpiredException::make();
        }

        return $participant;
    }
}
