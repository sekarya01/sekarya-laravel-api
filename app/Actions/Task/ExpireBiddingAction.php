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
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

/**
 * Penutup lelang otomatis (G12).
 *
 * Feed sudah menyembunyikan task yang `bidding_closes_at`-nya lewat, tapi
 * statusnya tetap `open` sampai ada yang mengubahnya — dan `open` berarti
 * "masih menerima penawaran" di mata semua pembaca status. Command terjadwal
 * memanggil ini supaya statusnya ikut jujur.
 *
 * Pekerjaan hanya untuk task yang BELUM deal (`open`); sesudah deal transisi
 * ke `expired` memang tidak sah. Task banyak slot tetap `open` sampai slot
 * TERAKHIR terisi, jadi task yang sudah menerima sebagian pekerja tetap bisa
 * tersaring lewat `bidding_closes_at`.
 *
 * Dua tenggat: `bidding_closes_at` (kalau diisi), dan `needed_at` — jadwal
 * mulai yang lewat tanpa seorang pun diterima berarti tugasnya praktis gagal.
 * Yang sudah punya pekerja (`workers_hired` > 0) tidak disentuh lewat jalur
 * `needed_at`: mereka sudah memegang tanggung jawabnya.
 *
 * Dana TIDAK dikembalikan di sini. `expired` bisa dibuka lagi lewat Ubah
 * (jadwal baru → `open`); dana baru kembali saat pemberi kerja membatalkan.
 */
final class ExpireBiddingAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TaskStatusRecorder $recorder,
        private readonly PushDispatcher $push,
    ) {}

    /**
     * [#impure] [#io: db, fcm] [#tx: own] [#lock: tasks] [#warn: satu transaksi per task, bukan sekaligus]
     *
     * @return int Jumlah task yang ditutup.
     */
    public function handle(?CarbonInterface $now = null): int
    {
        $now ??= now();
        $closed = 0;

        Task::query()
            ->where('status', TaskStatus::Open)
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q
                    ->whereNotNull('bidding_closes_at')
                    ->where('bidding_closes_at', '<=', $now))
                ->orWhere(fn ($q) => $q
                    ->whereNotNull('needed_at')
                    ->where('needed_at', '<=', $now)
                    ->where('workers_hired', 0)))
            ->orderBy('id')
            ->chunkById(100, function ($tasks) use (&$closed, $now): void {
                foreach ($tasks as $task) {
                    // Satu task satu transaksi, dibaca ulang di bawah kunci: task
                    // yang penawarannya diterima SETELAH potongan ini dibaca
                    // tidak boleh ditimpa `expired` dari salinan lama, dan
                    // kegagalan di tengah tidak boleh meninggalkan task
                    // `expired` dengan penawaran yang masih `pending`.
                    $bidders = $this->db->transaction(function () use ($task, $now): ?Collection {
                        $task = Task::query()->whereKey($task->getKey())->lockForUpdate()->firstOrFail();
                        if (! $this->isDue($task, $now)) {
                            return null;
                        }

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

                        $task->closePendingBids(BidStatus::Expired);

                        return $bidders;
                    });

                    if ($bidders === null) {
                        continue;
                    }

                    $this->push->send((int) $task->poster_id, PushMessages::taskExpired($task));

                    foreach ($bidders as $bidderId) {
                        $this->push->send($bidderId, PushMessages::bidExpired($task));
                    }

                    $closed++;
                }
            });

        return $closed;
    }

    /** Predikat sweep, diulang atas baris yang dikunci (SAMA dengan WHERE di atas). */
    private function isDue(Task $task, CarbonInterface $now): bool
    {
        if ($task->status !== TaskStatus::Open) {
            return false;
        }

        if ($task->bidding_closes_at !== null && $task->bidding_closes_at->lte($now)) {
            return true;
        }

        return $task->needed_at !== null
            && $task->needed_at->lte($now)
            && (int) $task->workers_hired === 0;
    }
}
