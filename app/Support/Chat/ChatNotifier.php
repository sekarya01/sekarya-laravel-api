<?php

declare(strict_types=1);

namespace App\Support\Chat;

use App\Enums\PushType;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\ChatRoom;
use App\Support\Push\PushDispatcher;
use App\Support\Push\PushMessages;

/**
 * FCM untuk chat — satu-satunya jalur realtime (tanpa WebSocket di hosting
 * bersama). Semua lewat `PushDispatcher::sendTransient`: tanpa baris lonceng,
 * sesudah commit.
 *
 *  - Pesan baru → notifikasi tampil ke peserta aktif lain yang TIDAK
 *    membisukan room; yang membisukan tetap menerima sinyal senyap supaya
 *    daftarnya tetap sinkron.
 *  - Sisanya → sinyal senyap (data-only) ke peserta aktif lain.
 */
final class ChatNotifier
{
    public function __construct(private readonly PushDispatcher $push) {}

    public function messageSent(ChatRoom $room, ChatMessage $message, ChatParticipant $sender): void
    {
        $task = $room->task;
        $loud = PushMessages::chatMessage($task, $room, $message, $sender);
        $quiet = PushMessages::chatSync(PushType::ChatMessage, $task, $room, [
            'message_id' => (string) $message->getKey(),
        ]);

        foreach ($this->recipients($room, $sender->user_id) as $participant) {
            $this->push->sendTransient(
                $participant->user_id,
                $participant->muted_at === null ? $loud : $quiet,
            );
        }
    }

    /** @param array<string, string> $extra */
    public function sync(ChatRoom $room, PushType $type, ?int $exceptUserId = null, array $extra = []): void
    {
        $message = PushMessages::chatSync($type, $room->task, $room, $extra);

        foreach ($this->recipients($room, $exceptUserId) as $participant) {
            $this->push->sendTransient($participant->user_id, $message);
        }
    }

    /** @return iterable<ChatParticipant> */
    private function recipients(ChatRoom $room, ?int $exceptUserId): iterable
    {
        return $room->participants()
            ->whereNull('left_at')
            ->when($exceptUserId !== null, fn ($q) => $q->where('user_id', '!=', $exceptUserId))
            ->get();
    }
}
