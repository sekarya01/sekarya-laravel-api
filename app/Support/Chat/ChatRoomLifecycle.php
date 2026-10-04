<?php

declare(strict_types=1);

namespace App\Support\Chat;

use App\Enums\ChatMessageType;
use App\Enums\ChatParticipantType;
use App\Enums\ChatRoomStatus;
use App\Enums\ChatRoomType;
use App\Enums\ChatSystemEvent;
use App\Enums\PushType;
use App\Enums\TaskStatus;
use App\Models\Bid;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\ChatRoom;
use App\Models\Task;

/**
 * Lahir dan berakhirnya room chat — DIKAITKAN ke dua titik tunggal yang
 * sudah ada, bukan ke Action satu per satu:
 *
 *  - `open()`   dipanggil `WorkOpening` (deal membuka pekerjaan). Tiga
 *               jalur pembuka pekerjaan otomatis ikut.
 *  - `expire()` dipanggil `TaskStatusRecorder` saat task masuk status akhir
 *               (completed/cancelled/refunded/expired). Room langsung
 *               `expired` — baca saja — tanpa masa tenggang (keputusan
 *               produk 2026-09-30).
 *
 * Idempoten: dipanggil dua kali tidak menggandakan room, peserta, maupun
 * pesan sistem.
 */
final class ChatRoomLifecycle
{
    public function __construct(private readonly ChatNotifier $notifier) {}

    public function open(Task $task): ChatRoom
    {
        $bids = $task->acceptedBids()->get();

        $room = ChatRoom::query()->withTrashed()->firstOrCreate(
            ['task_id' => $task->getKey()],
            ['type' => ChatRoomType::forTask((int) $task->workers_needed, $bids->count())],
        );

        if (! $room->wasRecentlyCreated) {
            return $room;
        }

        ChatParticipant::query()->create([
            'room_id' => $room->getKey(),
            'user_id' => $task->poster_id,
            'type' => ChatParticipantType::User,
            'joined_at' => now(),
        ]);

        $bids->each(fn (Bid $bid) => ChatParticipant::query()->create([
            'room_id' => $room->getKey(),
            'user_id' => $bid->bidder_id,
            'type' => ChatParticipantType::Worker,
            'joined_at' => now(),
        ]));

        $this->system($room, ChatSystemEvent::RoomCreated);

        return $room;
    }

    public function expire(Task $task, TaskStatus $status): void
    {
        $room = ChatRoom::query()
            ->where('task_id', $task->getKey())
            ->where('status', ChatRoomStatus::Active->value)
            ->lockForUpdate()
            ->first();

        if ($room === null) {
            return;
        }

        $this->system($room, ChatSystemEvent::forFinalStatus($status));

        $room->forceFill([
            'status' => ChatRoomStatus::Expired,
            'expired_at' => now(),
        ])->save();

        $this->notifier->sync($room, PushType::ChatRoomUpdated);
    }

    private function system(ChatRoom $room, ChatSystemEvent $event): ChatMessage
    {
        $message = ChatMessage::query()->create([
            'room_id' => $room->getKey(),
            'type' => ChatMessageType::System,
            'caption' => $event->fallbackCaption(),
            'system_event' => $event,
        ]);

        $room->forceFill(['last_message_id' => $message->getKey()])->save();

        return $message;
    }
}
