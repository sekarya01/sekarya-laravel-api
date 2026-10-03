<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Enums\PushType;
use App\Exceptions\Domain\ChatMessageNotOwnedException;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\User;
use App\Support\Chat\ChatAccess;
use App\Support\Chat\ChatNotifier;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Illuminate\Database\ConnectionInterface;

/**
 * Hapus pesan sendiri ("Hapus untuk semua").
 *
 * Kerangkanya tetap (soft delete) supaya urutan dan kutipan balasan tidak
 * rusak; isinya benar-benar hilang: caption dikosongkan, lampiran dan
 * BERKASNYA dihapus. Hanya selama room masih aktif.
 */
final class DeleteChatMessageAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly ChatAccess $access,
        private readonly ChatNotifier $notifier,
        private readonly Filesystem $storage,
    ) {}

    public function handle(ChatMessage $message, User $user): ChatMessage
    {
        $room = ChatRoom::query()->withTrashed()->findOrFail($message->room_id);
        $this->access->writer($room, $user);

        if ($message->sender_id !== $user->getKey()) {
            // Pesan sistem juga di sini: tidak ada yang memilikinya.
            throw ChatMessageNotOwnedException::make();
        }

        if ($message->trashed()) {
            return $this->present($message, $room);
        }

        $paths = $this->db->transaction(function () use ($message): array {
            $attachment = $message->attachment_id === null
                ? null
                : ChatAttachment::query()->find($message->attachment_id);

            $message->forceFill(['caption' => null, 'attachment_id' => null])->save();
            $message->delete();
            $attachment?->delete();

            return $attachment?->storedPaths() ?? [];
        });

        // Berkas dihapus SESUDAH commit: kalau transaksinya gagal, pesan
        // masih menunjuk ke berkas yang masih ada.
        if ($paths !== []) {
            $this->storage->disk('public')->delete($paths);
        }

        $this->notifier->sync($room, PushType::ChatMessageDeleted, $user->getKey(), [
            'message_id' => (string) $message->getKey(),
        ]);

        return $this->present($message, $room);
    }

    private function present(ChatMessage $message, ChatRoom $room): ChatMessage
    {
        $message->load(['attachment', 'replied.attachment']);

        return $message->setRelation('room', $room->load('participants.user'));
    }
}
