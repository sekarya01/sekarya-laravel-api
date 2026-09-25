<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Task;
use App\Support\Push\PushDispatcher;
use App\Support\Push\PushMessages;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Kabari mitra terdekat bahwa ada tugas baru (B13).
 *
 * Penerimanya SUDAH dihitung saat tugas diterbitkan (supaya `meta.notified_workers`
 * bisa dijawab di request yang sama); job ini hanya mengirim. Menyimpan daftar
 * id — bukan mengueri ulang — juga menjaga penerimanya tetap sama walau
 * ketersediaan mitra sempat berubah sebelum job berjalan.
 */
final class NotifyNearbyWorkersJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    /**
     * @param  list<int>  $userIds
     */
    public function __construct(
        private readonly string $taskUlid,
        private readonly array $userIds,
    ) {}

    public function handle(PushDispatcher $push): void
    {
        $task = Task::query()->where('ulid', $this->taskUlid)->first();

        // Tugas dibatalkan/dihapus sebelum job berjalan — tidak ada yang
        // perlu dikabari. Keadaan akhir yang sah, bukan kegagalan.
        if ($task === null) {
            return;
        }

        foreach ($this->userIds as $userId) {
            $push->send((int) $userId, PushMessages::taskPublished($task));
        }
    }
}
