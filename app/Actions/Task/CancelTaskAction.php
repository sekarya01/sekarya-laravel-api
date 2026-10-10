<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\ActivityStatus;
use App\Enums\ActorType;
use App\Enums\BidStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\TaskNotCancellableException;
use App\Models\Task;
use App\Models\User;
use App\Support\Push\PushDispatcher;
use App\Support\Push\PushMessages;
use App\Support\TaskSettlement;
use App\Support\TaskStatusRecorder;
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
        private readonly TaskSettlement $settlement,
        private readonly PushDispatcher $push,
    ) {}

    public function handle(Task $task, User $actor, ?string $reason = null): Task
    {
        // Status yang boleh dibatalkan = yang punya transisi ke `cancelled`
        // (lihat TaskStatus::allowedNext): `draft`, `open`, `expired`, `dealt`,
        // `active`. Tugas `expired` sengaja tidak di-refund otomatis — dananya
        // kembali di sini, saat pemberi kerja memutuskan membatalkan.
        // Di luar itu tolak SEBELUM menyentuh uang, supaya task yang sudah
        // selesai tidak bisa mengembalikan dana yang sudah dilepas.
        if (! in_array($task->status, [
            TaskStatus::Draft,
            TaskStatus::Open,
            TaskStatus::Expired,
            TaskStatus::Dealt,
            TaskStatus::Active,
        ], true)) {
            throw TaskNotCancellableException::becauseStatus($task->status);
        }

        return $this->db->transaction(function () use ($task, $actor, $reason): Task {
            // Sengketa mitra yang masih terbuka mengikat sebagian dana
            // tagihan: membatalkan sekarang mengembalikan dana yang mungkin
            // masih diputuskan pengelola sebagai upah mitra itu. Diperiksa di
            // bawah kunci task — pengajuan sengketa mengambil kunci yang sama.
            $this->settlement->lockTask($task);
            if ($task->activities()->where('status', ActivityStatus::Rejected)->exists()) {
                throw TaskNotCancellableException::becauseOpenDispute($task->status);
            }

            $isPoster = $task->poster_id === $actor->getKey();
            $actorType = $isPoster ? ActorType::Poster : ActorType::Worker;

            // Uang yang sudah ditahan wajib dikembalikan — SISANYA saja.
            // Upah mitra yang sudah disetujui sudah dibayar per mitra; kalau
            // seluruh tagihan dikembalikan, uang yang sama keluar dua kali.
            // Penerimanya PEMBAYAR (`payer_id`), bukan pembatalnya — pekerja
            // juga bisa membatalkan. Kalau belum ada uang masuk, tagihan cukup
            // dibatalkan.
            $payment = $task->payment;
            if ($payment?->status === PaymentStatus::Held) {
                $this->settlement->closePayment($task, $payment, 'Pengembalian dana task #'.$task->task_number);
            } elseif ($payment?->status === PaymentStatus::Pending) {
                $payment->forceFill([
                    'status' => PaymentStatus::Cancelled,
                    'cancelled_at' => now(),
                ])->save();
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

            $task->refresh();

            // Pekerja yang sudah diterima kehilangan pekerjaannya — mereka
            // diberi tahu (U12). Sumbernya penawaran `accepted`, bukan kolom
            // di task (satu task bisa merekrut banyak orang). Pembatalnya
            // sendiri tidak dikabari tentang aksinya sendiri.
            $workerIds = $task->workers()
                ->pluck('users.id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->reject(static fn (int $id): bool => $id === $actor->getKey());

            foreach ($workerIds as $workerId) {
                $this->push->send($workerId, PushMessages::taskCancelled($task));
            }

            return $task;
        });
    }
}
