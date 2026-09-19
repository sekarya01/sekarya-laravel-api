<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ActivityStatus;
use App\Enums\ActorType;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Bid;
use App\Models\Payment;
use App\Models\Task;
use Illuminate\Support\Collection;

/**
 * Membuka pekerjaan: satu activity per pekerja yang diterima, lalu task
 * berpindah ke `active`.
 *
 * Ada sebagai kelas tersendiri karena TIGA jalur memanggilnya, dan ketiganya
 * harus menghasilkan keadaan yang sama persis:
 *
 *  1. Perekrutan ditutup — jalur utama: deal membuka pekerjaannya.
 *  2. Pengelola mengonfirmasi transfer — untuk task yang barisnya belum ada
 *     (warisan sebelum aturan di atas berlaku).
 *  3. Penyusulan sekali jalan atas task warisan itu, lihat StuckWorkBackfill.
 *
 * Kalau logikanya disalin ke masing-masing, cepat atau lambat yang satu
 * diperbaiki dan yang lain tidak — dan selisihnya adalah pekerja yang punya
 * activity dengan harga yang salah.
 *
 * Yang SENGAJA tidak dikerjakan di sini: memindahkan status pembayaran. Siapa
 * yang boleh menyatakan dana sudah masuk adalah keputusan pemanggilnya, bukan
 * keputusan kelas ini.
 */
final class WorkOpening
{
    public function __construct(private readonly TaskStatusRecorder $recorder) {}

    /**
     * @return Collection<int, Activity>
     */
    public function open(
        Task $task,
        Payment $payment,
        ActorType $actorType,
        ?int $actorId,
        string $reason,
    ): Collection {
        $now = now();

        // `firstOrCreate` dengan penjaga unique (task_id, worker_id): satu
        // orang paling banyak satu activity per task, berapa kali pun jalur
        // ini terpanggil. Itu yang membuat kedua pemanggil aman dipanggil
        // berurutan — gerbang yang dinyalakan lagi setelah pekerjaan terbuka
        // tidak menggandakan apa pun.
        $activities = $task->acceptedBids()->get()->map(
            fn (Bid $bid): Activity => Activity::query()->firstOrCreate(
                [
                    'task_id' => $task->getKey(),
                    'worker_id' => $bid->bidder_id,
                ],
                [
                    'payment_id' => $payment->getKey(),
                    'status' => ActivityStatus::Open,
                    // Harga PER ORANG, dari penawarannya sendiri — bukan total
                    // task, yang pada task 30 orang akan membuat setiap
                    // pekerja terlihat berhak atas seluruh dana.
                    'agreed_amount' => $bid->amount,
                    'opened_at' => $now,
                ],
            ),
        );

        // Task yang SUDAH aktif tidak dipindahkan lagi: jalur kedua bisa
        // berjalan lebih dulu, dan `active → active` bukan transisi yang sah.
        if ($task->status !== TaskStatus::Active) {
            $this->recorder->move($task, TaskStatus::Active, $actorType, $actorId, reason: $reason);
        }

        return $activities;
    }
}
