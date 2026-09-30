<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\Actions\Admin\Chat\DeactivateChatRoomAction;
use App\Enums\ChatRoomStatus;
use App\Models\ChatRoom;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Nonaktifkan otomatis room yang sudah `expired` lebih lama dari
 * `sekarya.chat.purge_after_days`. 0 = mati. Idempoten; dijadwalkan harian.
 */
final class PurgeExpiredChatRoomsAction
{
    public function __construct(
        private readonly DeactivateChatRoomAction $deactivate,
        private readonly Config $config,
    ) {}

    public function handle(): int
    {
        $days = (int) $this->config->get('sekarya.chat.purge_after_days', 0);
        if ($days <= 0) {
            return 0;
        }

        $count = 0;
        ChatRoom::query()
            ->where('status', ChatRoomStatus::Expired->value)
            ->where('expired_at', '<=', now()->subDays($days))
            ->chunkById(100, function ($rooms) use (&$count): void {
                foreach ($rooms as $room) {
                    $this->deactivate->handle($room);
                    $count++;
                }
            });

        return $count;
    }
}
