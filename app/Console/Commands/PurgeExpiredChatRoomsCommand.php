<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Chat\PurgeExpiredChatRoomsAction;
use Illuminate\Console\Command;

/**
 * Nonaktifkan room chat yang sudah `expired` melewati masa simpan
 * (`sekarya.chat.purge_after_days`) — pesan & lampirannya DIHAPUS PERMANEN.
 * Dijadwalkan harian di `routes/console.php`.
 */
final class PurgeExpiredChatRoomsCommand extends Command
{
    protected $signature = 'sekarya:chat:purge-expired';

    protected $description = 'Hapus permanen isi room chat yang sudah expired melewati masa simpan';

    public function handle(PurgeExpiredChatRoomsAction $action): int
    {
        $purged = $action->handle();

        $this->info("Room chat dinonaktifkan: {$purged}.");

        return self::SUCCESS;
    }
}
