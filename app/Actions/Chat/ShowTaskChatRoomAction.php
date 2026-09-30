<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Enums\ChatRoomStatus;
use App\Models\ChatRoom;
use App\Models\Task;
use App\Models\User;

/**
 * Room milik sebuah task, untuk tombol "Chat" di Detail Tugas.
 *
 * `null` (bukan galat) bila belum ada room, pemanggil bukan pesertanya, atau
 * room sudah dinonaktifkan — ketiganya berarti "tidak ada chat untuk Anda di
 * sini", dan layar detail cukup menyembunyikan tombolnya.
 */
final class ShowTaskChatRoomAction
{
    public function handle(Task $task, User $user): ?ChatRoom
    {
        return ChatRoom::query()
            ->where('task_id', $task->getKey())
            ->where('status', '!=', ChatRoomStatus::Deactivated->value)
            ->visibleTo($user)
            ->forViewer($user)
            ->first();
    }
}
