<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Enums\PushType;
use App\Exceptions\Domain\ChatMessageNotFoundException;
use App\Models\ChatMessage;
use App\Models\ChatParticipant;
use App\Models\ChatRoom;
use App\Models\User;
use App\Support\Chat\ChatAccess;
use App\Support\Chat\ChatNotifier;
use Illuminate\Database\ConnectionInterface;

/**
 * Majukan penanda "sudah sampai" dan/atau "sudah dibaca" milik sendiri.
 *
 * Penanda hanya MAJU: id yang lebih lama dari penanda sekarang diabaikan
 * (perangkat kedua yang tertinggal tidak memundurkan tanda baca). Dibaca
 * selalu berarti sudah sampai. Berubah = sinyal senyap `chat_receipt` ke
 * peserta lain supaya centang di layar pengirim ikut berubah.
 */
final class UpdateChatReceiptsAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly ChatAccess $access,
        private readonly ChatNotifier $notifier,
    ) {}

    public function handle(ChatRoom $room, User $user, ?string $deliveredId, ?string $readId): ChatParticipant
    {
        $changed = $this->db->transaction(function () use ($room, $user, $deliveredId, $readId): bool {
            $this->access->participant($room, $user);
            $participant = ChatParticipant::query()
                ->where('room_id', $room->getKey())
                ->where('user_id', $user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $read = $this->forward($participant->last_read_message_id, $this->messageIn($room, $readId));
            $delivered = $this->forward(
                $participant->last_delivered_message_id,
                $this->forward($this->messageIn($room, $deliveredId), $read),
            );

            $participant->forceFill([
                'last_read_message_id' => $read,
                'last_delivered_message_id' => $delivered,
            ]);
            if ($participant->isDirty('last_read_message_id')) {
                $participant->last_read_at = now();
            }

            $dirty = $participant->isDirty();
            $participant->save();

            return $dirty;
        });

        if ($changed) {
            $this->notifier->sync($room, PushType::ChatReceipt, $user->getKey(), [
                'user_id' => (string) $user->ulid,
            ]);
        }

        return ChatParticipant::query()
            ->where('room_id', $room->getKey())
            ->where('user_id', $user->getKey())
            ->firstOrFail();
    }

    private function messageIn(ChatRoom $room, ?string $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $exists = ChatMessage::query()->withTrashed()
            ->whereKey(strtoupper($id))
            ->where('room_id', $room->getKey())
            ->exists();

        return $exists ? strtoupper($id) : throw ChatMessageNotFoundException::make();
    }

    /** ULID urut waktu: yang lebih besar = lebih baru. */
    private function forward(?string $current, ?string $candidate): ?string
    {
        if ($candidate === null) {
            return $current;
        }

        return $current === null || strcmp($candidate, $current) > 0 ? $candidate : $current;
    }
}
