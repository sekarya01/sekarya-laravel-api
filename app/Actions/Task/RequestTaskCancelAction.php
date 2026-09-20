<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\CancelApprovalStatus;
use App\Enums\CancelRequestStatus;
use App\Exceptions\Domain\CancelRequestPendingException;
use App\Exceptions\Domain\NoWorkersHiredException;
use App\Models\Task;
use App\Models\TaskCancelRequest;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;

/**
 * Pemberi kerja MEMINTA pembatalan setelah ada pekerja yang deal.
 *
 * Dua jalur pembatalan, dipilih dari keadaan — bukan dari tombol:
 *
 * - Belum ada pekerja (`workers_hired == 0`) → `CancelTaskAction` LANGSUNG
 *   lewat `POST tasks/{task}/cancel`. Tidak ada yang perlu dimintai setuju.
 * - Sudah ada yang deal → ke sini. Action ini MENOLAK bila task-nya belum
 *   deal (`no_workers_hired`, lewat `DomainException` anonim? tidak — lewat
 *   `TaskNotDealt`: tidak ada; dipakai `NoWorkersHiredException` yang sudah
 *   ada), dan MENOLAK bila masih ada permintaan `pending`
 *   (`cancel_request_pending`).
 */
final class RequestTaskCancelAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(Task $task, User $requester, ?string $reason = null): TaskCancelRequest
    {
        return $this->db->transaction(function () use ($task, $requester, $reason): TaskCancelRequest {
            if ($task->workers_hired <= 0) {
                throw NoWorkersHiredException::forCancelRequest();
            }

            $pending = TaskCancelRequest::query()
                ->where('task_id', $task->getKey())
                ->where('status', CancelRequestStatus::Pending)
                ->lockForUpdate()
                ->exists();

            if ($pending) {
                throw new CancelRequestPendingException;
            }

            $created = TaskCancelRequest::query()->create([
                'task_id' => $task->getKey(),
                'requested_by' => $requester->getKey(),
                'reason' => $reason,
                'status' => CancelRequestStatus::Pending,
            ]);

            // Daftar penjawab DIKUNCI di sini. Pekerja yang diterima sesudah
            // permintaan dibuat tidak ikut menentukan: kalau daftarnya boleh
            // bertambah di tengah jalan, kebulatan yang sudah tercapai bisa
            // dibatalkan lagi oleh orang yang baru masuk.
            //
            // Peminta dikecualikan bila ia sendiri pekerja di task ini —
            // menunggu seseorang menyetujui permintaannya sendiri akan
            // menggantung selamanya di satu suara yang tak pernah ia berikan.
            $workerIds = $task->workers()
                ->pluck('users.id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->reject(static fn (int $id): bool => $id === $requester->getKey())
                ->values();

            $created->approvals()->createMany(
                $workerIds->map(static fn (int $id): array => [
                    'worker_id' => $id,
                    'status' => CancelApprovalStatus::Pending,
                ])->all(),
            );

            return $created;
        });
    }
}
