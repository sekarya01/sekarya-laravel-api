<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\ActivityStatus;
use App\Enums\ActorType;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Activity;
use App\Models\Bid;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskStatusRecorder;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

/**
 * Transfer pemberi kerja diterima → dana ditahan → ACTIVITY DIBUKA.
 *
 * STUB: belum ada gateway. Untuk sekarang dipicu langsung; nanti dipicu webhook.
 * Yang penting tetap sama apa pun pemicunya: activity hanya boleh terbuka
 * ketika dana benar-benar ditahan.
 *
 * Satu task bisa merekrut banyak orang, jadi satu transfer membuka BANYAK
 * activity — satu per pekerja yang diterima, masing-masing membawa harga
 * penawarannya sendiri. Tagihannya tetap satu baris sebesar jumlah semuanya,
 * karena pemberi kerja mentransfer sekali.
 */
final class HoldPaymentAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TaskStatusRecorder $recorder
    ) {}

    /** @return Collection<int, Activity> */
    public function handle(Task $task, User $payer): Collection
    {
        return $this->db->transaction(function () use ($task, $payer): Collection {
            // Row lock sungguhan di InnoDB: mencegah dua konfirmasi transfer
            // paralel sama-sama membuka activity. unique (payment_id) di
            // activities tetap jadi pengaman terakhir.
            // Tagihan sudah ada sejak pelamar PERTAMA diterima, jadi tanpa
            // penjaga ini pemberi kerja bisa menahan dana saat perekrutan
            // belum selesai — dan gagalnya baru muncul di baris terakhir
            // sebagai "tidak bisa berpindah dari open ke active", yang tidak
            // menjelaskan apa pun. Ditolak di depan, dengan status task apa
            // adanya.
            if ($task->status->acceptsBids()) {
                throw InvalidStatusTransitionException::between(
                    $task->status->value,
                    TaskStatus::Active->value,
                );
            }

            $payment = Payment::query()
                ->where('task_id', $task->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $payment->status->canTransitionTo(PaymentStatus::Held)) {
                throw InvalidStatusTransitionException::between(
                    $payment->status->value,
                    PaymentStatus::Held->value,
                );
            }

            $now = now();

            $payment->forceFill([
                'status' => PaymentStatus::Held,
                'paid_at' => $now,
                'held_at' => $now,
            ])->save();

            // Baris activity inilah yang menegakkan aturan "tidak ada activity
            // tanpa dana ditahan" — bukan disiplin kode. Pembayarannya satu
            // untuk semua pekerja, jadi penjaganya bukan lagi unique
            // (payment_id) melainkan unique (task_id, worker_id): satu orang
            // paling banyak satu activity per task, berapa kali pun endpoint
            // ini terpanggil.
            $activities = $task->acceptedBids()->get()->map(
                fn (Bid $bid): Activity => Activity::query()->firstOrCreate(
                    [
                        'task_id' => $task->getKey(),
                        'worker_id' => $bid->bidder_id,
                    ],
                    [
                        'payment_id' => $payment->getKey(),
                        'status' => ActivityStatus::Open,
                        // Harga PER ORANG, dari penawarannya sendiri — bukan
                        // total task, yang pada task 30 orang akan membuat
                        // setiap pekerja terlihat berhak atas seluruh dana.
                        'agreed_amount' => $bid->amount,
                        'opened_at' => $now,
                    ],
                ),
            );

            $this->recorder->move(
                $task,
                TaskStatus::Active,
                ActorType::Poster,
                $payer->getKey(),
                reason: sprintf('dana ditahan, %d activity dibuka', $activities->count()),
            );

            return $activities;
        });
    }
}
