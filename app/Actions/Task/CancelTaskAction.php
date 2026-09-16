<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\ActorType;
use App\Enums\BidStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Enums\WalletEntryType;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskStatusRecorder;
use App\Support\WalletLedger;
use Illuminate\Database\ConnectionInterface;

/**
 * Pembatalan task, dan pengembalian uang yang menyertainya.
 *
 * Dana yang sudah `held` kembali KE SALDO pemberi kerja, bukan ke rekeningnya.
 * Itu keputusan, bukan jalan pintas: transfer balik ke bank menuntut antrean
 * manual ketiga dan menahan uang orang selama antrean itu berjalan, padahal
 * hampir semua pembatalan diikuti task pengganti. Yang menginginkan uangnya
 * kembali ke bank memakai pintu yang sama dengan pekerja —
 * `POST /me/wallet/withdrawals`.
 */
final class CancelTaskAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TaskStatusRecorder $recorder,
        private readonly WalletLedger $ledger,
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
                $wasHeld = $payment->status === PaymentStatus::Held;

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

                // `refunded` berhenti jadi sekadar penanda status sejak ada
                // saldo: uang yang benar-benar diterima harus benar-benar
                // kembali ke seseorang. Yang menerimanya PEMBAYARNYA
                // (`payer_id`), bukan pembatalnya — pekerja juga bisa
                // membatalkan, dan mengembalikan dana ke pembatal akan
                // memindahkan uang pemberi kerja ke orang lain.
                if ($wasHeld) {
                    $this->ledger->credit(
                        $this->ledger->walletFor($payment->payer),
                        WalletEntryType::Refund,
                        (int) $payment->amount,
                        $payment,
                        'Pengembalian dana task #'.$task->task_number,
                    );
                }
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
