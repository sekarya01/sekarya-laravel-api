<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\ActorType;
use App\Enums\BidStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Support\Push\PushDispatcher;
use App\Support\Push\PushMessages;
use App\Support\TaskEscrow;
use App\Support\TaskStatusRecorder;
use Carbon\CarbonInterface;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

/**
 * Penutup lelang otomatis (G12).
 *
 * Feed sudah menyembunyikan task yang tenggatnya lewat, tapi statusnya tetap
 * `open` sampai ada yang mengubahnya — dan `open` berarti "masih menerima
 * penawaran" di mata semua pembaca status. Command terjadwal memanggil ini
 * supaya statusnya ikut jujur.
 *
 * Hanya untuk task yang BELUM deal (`open`); sesudah deal transisi ke
 * `expired` tidak sah. Dua tenggat, diperlakukan sama: `bidding_closes_at`
 * (kalau diisi) dan `needed_at` (jadwal mulai).
 *
 * Task banyak slot tetap `open` sampai slot TERAKHIR terisi, jadi tenggat bisa
 * lewat padahal sebagian pekerja sudah diterima. Keputusan pemilik 2026-10-13:
 *
 *  1. `expiry_warning_minutes` sebelum tenggat, pemberi kerja diperingatkan
 *     SEKALI (`task_expiring`): mulai dengan pekerja yang ada
 *     (`POST /tasks/{task}/start`) atau biarkan kedaluwarsa.
 *  2. Lewat tenggat tanpa dimulai → `expired`, pekerja yang diterima
 *     DIBATALKAN (bid → `expired`) dan diberi tahu, dan SELURUH dana kembali
 *     ke saldo (`TaskEscrow::release`, tagihan tetap `held` bernominal 0 —
 *     dibuka lagi lewat Ubah, dananya ditagih ulang). Tanpa catatan
 *     pembatalan bagi siapa pun: ini kedaluwarsa oleh sistem.
 *
 * Task TANPA pekerja tetap aturan lama: dana ditahan sampai dibuka lagi lewat
 * Ubah atau dibatalkan pemberi kerja.
 */
final class ExpireBiddingAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TaskStatusRecorder $recorder,
        private readonly TaskEscrow $escrow,
        private readonly PushDispatcher $push,
    ) {}

    /**
     * [#impure] [#io: db, fcm] [#tx: own] [#lock: tasks, payments, wallets] [#warn: satu transaksi per task, bukan sekaligus]
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
                    ->where('needed_at', '<=', $now)))
            ->orderBy('id')
            ->chunkById(100, function ($tasks) use (&$closed, $now): void {
                foreach ($tasks as $task) {
                    // Satu task satu transaksi, dibaca ulang di bawah kunci: task
                    // yang penawarannya diterima SETELAH potongan ini dibaca
                    // tidak boleh ditimpa `expired` dari salinan lama, dan
                    // kegagalan di tengah tidak boleh meninggalkan task
                    // `expired` dengan penawaran yang masih `pending`.
                    $notified = $this->db->transaction(function () use ($task, $now): ?array {
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
                        $bidders = $this->biddersWith($task, BidStatus::Pending);
                        $task->closePendingBids(BidStatus::Expired);

                        $hired = $this->biddersWith($task, BidStatus::Accepted);
                        if ($hired->isNotEmpty()) {
                            $this->dropHiredWorkers($task);
                        }

                        return ['bidders' => $bidders, 'hired' => $hired];
                    });

                    if ($notified === null) {
                        continue;
                    }

                    $this->push->send((int) $task->poster_id, PushMessages::taskExpired($task));

                    foreach ($notified['bidders'] as $bidderId) {
                        $this->push->send($bidderId, PushMessages::bidExpired($task));
                    }

                    foreach ($notified['hired'] as $workerId) {
                        $this->push->send($workerId, PushMessages::hiredWorkExpired($task));
                    }

                    $closed++;
                }
            });

        $this->warnPostersWithHiredWorkers($now);

        return $closed;
    }

    /**
     * Pekerja yang sudah diterima dibatalkan, dan dana kembali ke saldo.
     *
     * Bid `expired`, bukan `rejected`: pekerjanya tidak ditolak — tugasnya
     * yang berhenti. Penghitung diturunkan di sini juga, supaya task yang
     * dibuka lagi mulai dari nol slot terisi dan bukan dari angka basi.
     */
    private function dropHiredWorkers(Task $task): void
    {
        $task->bids()
            ->where('status', BidStatus::Accepted)
            ->update([
                'status' => BidStatus::Expired,
                'responded_at' => now(),
                'updated_at' => now(),
            ]);

        $task->forceFill(['workers_hired' => 0, 'agreed_amount' => 0])->save();

        $this->escrow->release($task, 'tugas kedaluwarsa, pekerja dibatalkan');
    }

    /**
     * Peringatan SEKALI per tenggat (`expiry_warned_at`), hanya untuk task
     * yang sudah punya pekerja — yang belum punya tidak kehilangan apa pun.
     */
    private function warnPostersWithHiredWorkers(CarbonInterface $now): void
    {
        $minutes = (int) config('sekarya.tasks.expiry_warning_minutes');
        if ($minutes <= 0) {
            return;
        }
        $soon = $now->copy()->addMinutes($minutes);

        Task::query()
            ->where('status', TaskStatus::Open)
            ->where('workers_hired', '>', 0)
            ->whereNull('expiry_warned_at')
            ->where(fn ($query) => $query
                ->whereBetween('bidding_closes_at', [$now, $soon])
                ->orWhereBetween('needed_at', [$now, $soon]))
            ->orderBy('id')
            ->chunkById(100, function ($tasks) use ($now, $soon, $minutes): void {
                foreach ($tasks as $task) {
                    $warned = $this->db->transaction(function () use ($task, $now, $soon): ?Task {
                        $task = Task::query()->whereKey($task->getKey())->lockForUpdate()->firstOrFail();
                        if ($task->status !== TaskStatus::Open
                            || (int) $task->workers_hired === 0
                            || $task->expiry_warned_at !== null
                            || $this->deadline($task) === null
                            || $this->deadline($task)->gt($soon)) {
                            return null;
                        }

                        $task->forceFill(['expiry_warned_at' => $now])->save();

                        return $task;
                    });

                    if ($warned !== null) {
                        $this->push->send((int) $warned->poster_id, PushMessages::taskExpiring($warned, $minutes));
                    }
                }
            });
    }

    /** Tenggat terdekat dari keduanya, atau null bila tak ada. */
    private function deadline(Task $task): ?CarbonInterface
    {
        $dates = array_filter([$task->bidding_closes_at, $task->needed_at]);

        return $dates === [] ? null : min($dates);
    }

    /** @return Collection<int, int> */
    private function biddersWith(Task $task, BidStatus $status): Collection
    {
        return $task->bids()
            ->where('status', $status)
            ->pluck('bidder_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
    }

    /** Predikat sweep, diulang atas baris yang dikunci (SAMA dengan WHERE di atas). */
    private function isDue(Task $task, CarbonInterface $now): bool
    {
        if ($task->status !== TaskStatus::Open) {
            return false;
        }

        $deadline = $this->deadline($task);

        return $deadline !== null && $deadline->lte($now);
    }
}
