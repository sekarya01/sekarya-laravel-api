<?php

declare(strict_types=1);

namespace App\Actions\Admin\Chat;

use App\Enums\AdminAction;
use App\Enums\ChatRoomStatus;
use App\Enums\PushType;
use App\Models\Admin;
use App\Models\ChatAttachment;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Support\AdminAuditRecorder;
use App\Support\Chat\ChatNotifier;
use Illuminate\Contracts\Filesystem\Factory as Filesystem;
use Illuminate\Database\ConnectionInterface;

/**
 * Nonaktifkan room: SELURUH pesan dan lampiran (baris + berkas) dihapus
 * permanen. Tidak bisa dibatalkan.
 *
 * Dua pemanggil: pengelola (moderasi, wajib alasan, tercatat di jejak audit
 * di dalam transaksi yang sama) dan pembersihan otomatis room `expired`
 * (`$admin` null, tanpa jejak — bukan tindakan pengelola).
 *
 * Baris room + pesertanya DISISAKAN (soft delete) supaya peserta mendapat
 * 410 `chat_room_deactivated`, bukan 404, dan task tidak dibukakan room
 * kedua. Idempoten: room yang sudah nonaktif dikembalikan apa adanya.
 */
final class DeactivateChatRoomAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly AdminAuditRecorder $audit,
        private readonly ChatNotifier $notifier,
        private readonly Filesystem $storage,
    ) {}

    public function handle(ChatRoom $room, ?Admin $admin = null, ?string $reason = null): ChatRoom
    {
        $paths = $this->db->transaction(function () use ($room, $admin, $reason): ?array {
            $locked = ChatRoom::query()->withTrashed()->whereKey($room->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === ChatRoomStatus::Deactivated) {
                return null;
            }

            $paths = ChatAttachment::query()->where('room_id', $locked->getKey())->pluck('path')->all();

            // Pesan dulu (FK attachment_id), baru lampirannya.
            ChatMessage::query()->withTrashed()->where('room_id', $locked->getKey())->forceDelete();
            ChatAttachment::query()->where('room_id', $locked->getKey())->delete();

            $locked->forceFill([
                'status' => ChatRoomStatus::Deactivated,
                'deactivated_at' => now(),
                'last_message_id' => null,
            ])->save();
            $locked->delete();

            if ($admin !== null) {
                $this->audit->record($admin, AdminAction::ChatRoomDeactivated, (int) $locked->getKey(), $reason);
            }

            return $paths;
        });

        $fresh = ChatRoom::query()->withTrashed()->findOrFail($room->getKey());

        if ($paths !== null) {
            if ($paths !== []) {
                $this->storage->disk('public')->delete($paths);
            }

            $this->notifier->sync($fresh, PushType::ChatRoomDeactivated);
        }

        return $fresh;
    }
}
