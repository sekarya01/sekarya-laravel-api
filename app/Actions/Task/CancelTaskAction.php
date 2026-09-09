<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\ActorType;
use App\Enums\BidStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskStatusRecorder;
use Illuminate\Database\ConnectionInterface;

final class CancelTaskAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TaskStatusRecorder $recorder
    ) {}

    public function handle(Task $task, User $actor, ?string $reason = null): Task
    {
        return $this->db->transaction(function () use ($task, $actor, $reason): Task {
            $isPoster = $task->poster_id === $actor->getKey();
            $actorType = $isPoster ? ActorType::Poster : ActorType::Worker;

            // Uang yang sudah ditahan wajib dikembalikan. Kalau belum masuk,
            // pembayaran cukup dibatalkan.
            $payment = $task->payment;
            if ($payment !== null) {
                $payment->forceFill(match ($payment->status) {
                    PaymentStatus::Held => [
                        'status' => PaymentStatus::Refunded,
                        'refunded_at' => now(),
                    ],
                    PaymentStatus::Pending => [
                        'status' => PaymentStatus::Cancelled,
                        'cancelled_at' => now(),
                    ],
                    default => [],
                })->save();
            }

            // Penawaran yang masih menggantung ditutup agar tidak muncul di daftar worker.
            $task->bids()->where('status', BidStatus::Pending)->update([
                'status' => BidStatus::Rejected,
                'responded_at' => now(),
                'updated_at' => now(),
            ]);

            $task->forceFill([
                'cancelled_at' => now(),
                'cancelled_by' => $actorType->value,
                'cancellation_reason' => $reason,
            ])->save();

            $this->recorder->move(
                $task,
                TaskStatus::Cancelled,
                $actorType,
                $actor->getKey(),
                $reason,
            );

            // Pembatalan setelah deal adalah sinyal risiko pada orangnya.
            if ($task->dealt_at !== null) {
                $actor->increment('cancellations');
            }

            return $task->refresh();
        });
    }
}
