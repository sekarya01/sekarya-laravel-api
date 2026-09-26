<?php

declare(strict_types=1);

namespace App\Actions\Admin\Dispute;

use App\Enums\ActorType;
use App\Enums\ActivityStatus;
use App\Enums\BidStatus;
use App\Enums\DisputeResolution;
use App\Enums\DisputeStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Enums\WalletEntryType;
use App\Exceptions\Domain\DisputeAlreadyResolvedException;
use App\Exceptions\Domain\DisputeNotAllowedException;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TaskDispute;
use App\Support\Push\PushDispatcher;
use App\Support\Push\PushMessages;
use App\Support\TaskStatusRecorder;
use App\Support\WalletLedger;
use App\Support\WorkerPayout;
use Illuminate\Database\ConnectionInterface;

/**
 * Putuskan sengketa (G5): dana dilepas ke pekerja, atau dikembalikan ke
 * pemberi kerja.
 *
 * Inilah jalan keluar dari status `disputed` yang sebelumnya buntu. Dua
 * keputusan, dua akibat uang yang berbeda:
 *
 *  - `release` → task `completed`; setiap pekerja menerima `agreed_amount`
 *    miliknya (dari `activities`), dan activity yang masih tertahan
 *    (mis. `rejected` oleh pemberi kerja) dinaikkan ke `approved`.
 *  - `refund`  → task `refunded`; dana yang ditahan kembali ke saldo pemberi
 *    kerja, penawaran menggantung ditolak.
 *
 * Idempotensi dijaga dua lapis: status tiket (`open` → `resolved`) dan unique
 * `(reference_type, reference_id, type)` di buku besar.
 */
final class ResolveDisputeAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly WalletLedger $ledger,
        private readonly WorkerPayout $payout,
        private readonly TaskStatusRecorder $recorder,
        private readonly PushDispatcher $push,
    ) {}

    public function handle(
        TaskDispute $dispute,
        Admin $admin,
        DisputeResolution $resolution,
        ?string $note = null,
    ): TaskDispute {
        return $this->db->transaction(function () use ($dispute, $admin, $resolution, $note): TaskDispute {
            $fresh = TaskDispute::query()->whereKey($dispute->getKey())->lockForUpdate()->firstOrFail();

            if (! $fresh->status->isOpen()) {
                throw DisputeAlreadyResolvedException::make();
            }

            $task = Task::query()->whereKey($fresh->task_id)->lockForUpdate()->firstOrFail();

            if ($task->status !== TaskStatus::Disputed) {
                throw DisputeNotAllowedException::becauseStatus($task->status->value);
            }

            $payment = Payment::query()->where('task_id', $task->getKey())->lockForUpdate()->first();
            $now = now();

            if ($resolution === DisputeResolution::Release) {
                $this->release($task, $payment, $now);
                $this->recorder->move($task, TaskStatus::Completed, ActorType::Admin, (int) $admin->getKey(), 'Sengketa: dana dilepas');
            } else {
                $this->refund($task, $payment, $now);
                $this->recorder->move($task, TaskStatus::Refunded, ActorType::Admin, (int) $admin->getKey(), 'Sengketa: dana dikembalikan');
            }

            $fresh->forceFill([
                'status' => DisputeStatus::Resolved,
                'resolution' => $resolution,
                'resolved_by' => $admin->getKey(),
                'resolved_at' => $now,
                'admin_note' => $note,
            ])->save();

            $this->notify($task, $resolution === DisputeResolution::Release);

            return $fresh;
        });
    }

    private function release(Task $task, ?Payment $payment, \DateTimeInterface $now): void
    {
        $task->forceFill(['completed_at' => $now])->save();

        if ($payment !== null && $payment->status === PaymentStatus::Held) {
            $payment->forceFill([
                'status' => PaymentStatus::Released,
                'released_at' => $now,
            ])->save();

            $task->activities()->with('worker')->get()->each(function (Activity $activity) use ($now): void {
                if ($activity->agreed_amount === null) {
                    return;
                }

                if ($activity->status !== ActivityStatus::Approved) {
                    $activity->forceFill([
                        'status' => ActivityStatus::Approved,
                        'approved_at' => $now,
                    ])->save();

                    $activity->worker->workerProfileOrCreate()->increment('tasks_completed');
                }

                $this->payout->pay($activity, 'Upah sengketa task #'.$task->task_number);
            });
        }
    }

    private function refund(Task $task, ?Payment $payment, \DateTimeInterface $now): void
    {
        if ($payment !== null && $payment->status === PaymentStatus::Held) {
            $payment->forceFill([
                'status' => PaymentStatus::Refunded,
                'refunded_at' => $now,
            ])->save();

            // Dana kembali ke PEMBAYAR (poster), sama seperti pembatalan.
            $this->ledger->credit(
                $this->ledger->walletFor($payment->payer),
                WalletEntryType::Refund,
                (int) $payment->amount,
                $payment,
                'Pengembalian dana sengketa task #'.$task->task_number,
            );
        }

        $task->bids()->where('status', BidStatus::Pending)->update([
            'status' => BidStatus::Rejected,
            'responded_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function notify(Task $task, bool $released): void
    {
        $recipients = collect([(int) $task->poster_id])
            ->merge(
                $task->bids()
                    ->where('status', BidStatus::Accepted)
                    ->pluck('bidder_id')
                    ->map(static fn (mixed $id): int => (int) $id),
            )
            ->unique();

        foreach ($recipients as $userId) {
            $this->push->send($userId, PushMessages::disputeResolved($task, $released));
        }
    }
}
