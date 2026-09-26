<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\ActorType;
use App\Enums\BidStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Support\Push\PushDispatcher;
use App\Support\Push\PushMessages;
use App\Support\TaskStatusRecorder;
use Carbon\CarbonInterface;

/**
 * Penutup lelang otomatis (G12).
 *
 * Feed sudah menyembunyikan task yang `bidding_closes_at`-nya lewat, tapi
 * statusnya tetap `open` sampai ada yang mengubahnya — dan `open` berarti
 * "masih menerima penawaran" di mata semua pembaca status. Command terjadwal
 * memanggil ini supaya statusnya ikut jujur.
 *
 * Pekerjaan hanya untuk task yang BELUM deal: begitu ada penawaran diterima,
 * status bukan lagi `open` dan transisi ke `expired` memang tidak sah.
 */
final class ExpireBiddingAction
{
    public function __construct(
        private readonly TaskStatusRecorder $recorder,
        private readonly PushDispatcher $push,
    ) {}

    /** @return int Jumlah task yang ditutup. */
    public function handle(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $closed = 0;

        Task::query()
            ->where('status', TaskStatus::Open)
            ->whereNotNull('bidding_closes_at')
            ->where('bidding_closes_at', '<=', $now)
            ->orderBy('id')
            ->chunkById(100, function ($tasks) use (&$closed): void {
                foreach ($tasks as $task) {
                    $this->recorder->move(
                        $task,
                        TaskStatus::Expired,
                        ActorType::System,
                        null,
                        'Batas waktu penawaran terlewat',
                    );

                    // Penawaran yang masih menggantung ditutup DAN penawarnya
                    // diberi tahu — kalau tidak, mereka menunggu jawaban atas
                    // lelang yang sudah mati. Sama seperti pembatalan task.
                    $bidders = $task->bids()
                        ->where('status', BidStatus::Pending)
                        ->pluck('bidder_id')
                        ->map(static fn (mixed $id): int => (int) $id)
                        ->unique();

                    $task->bids()
                        ->where('status', BidStatus::Pending)
                        ->update([
                            'status' => BidStatus::Expired,
                            'responded_at' => now(),
                            'updated_at' => now(),
                        ]);

                    $this->push->send((int) $task->poster_id, PushMessages::taskExpired($task));

                    foreach ($bidders as $bidderId) {
                        $this->push->send($bidderId, PushMessages::bidExpired($task));
                    }

                    $closed++;
                }
            });

        return $closed;
    }
}
