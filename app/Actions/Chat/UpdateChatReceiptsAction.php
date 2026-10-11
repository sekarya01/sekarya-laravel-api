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
 * selalu berarti sudah sampai. Berubah = sinyal senyap `chat_receipt` HANYA
 * ke PENGIRIM pesan yang statusnya berubah — centang hanya berarti bagi
 * pengirimnya. Dulu ke semua peserta: satu pesan di grup 30 orang menjadi
 * ±1.700 push senyap (tiap laporan × tiap peserta, untuk terkirim DAN
 * dibaca), padahal push chat dikirim tanpa antrean.
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
        $senders = $this->db->transaction(function () use ($room, $user, $deliveredId, $readId): array {
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

            $oldRead = $participant->last_read_message_id;
            $oldDelivered = $participant->last_delivered_message_id;

            $participant->forceFill([
                'last_read_message_id' => $read,
                'last_delivered_message_id' => $delivered,
            ]);
            if ($participant->isDirty('last_read_message_id')) {
                $participant->last_read_at = now();
            }

            if (! $participant->isDirty()) {
                return [];
            }
            $participant->save();

            return $this->sendersBetween($room, $user, [[$oldDelivered, $delivered], [$oldRead, $read]]);
        });

        if ($senders !== []) {
            $this->notifier->syncTo($room, PushType::ChatReceipt, $senders, [
                'user_id' => (string) $user->ulid,
            ]);
        }

        return ChatParticipant::query()
            ->where('room_id', $room->getKey())
            ->where('user_id', $user->getKey())
            ->firstOrFail();
    }

    /**
     * Pengirim (selain [reader], selain pesan sistem) dari pesan yang statusnya
     * baru berubah: id dalam rentang `(lama, baru]` tiap penanda. Id pesan ULID
     * (urut waktu), jadi rentang id = rentang pesan; dipindai lewat indeks
     * (room_id, id).
     *
     * @param  list<array{0: ?string, 1: ?string}>  $ranges
     * @return list<int>
     */
    private function sendersBetween(ChatRoom $room, User $reader, array $ranges): array
    {
        $ranges = array_filter($ranges, static fn (array $r): bool => $r[1] !== null && $r[1] !== $r[0]);
        if ($ranges === []) {
            return [];
        }

        return ChatMessage::query()
            ->where('room_id', $room->getKey())
            ->whereNotNull('sender_id')
            ->where('sender_id', '!=', $reader->getKey())
            ->where(function ($q) use ($ranges): void {
                foreach ($ranges as [$from, $to]) {
                    $q->orWhere(fn ($r) => $r->where('id', '>', $from ?? '')->where('id', '<=', $to));
                }
            })
            ->distinct()
            ->pluck('sender_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
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
