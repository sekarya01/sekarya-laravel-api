<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\CancelRequestStatus;
use App\Exceptions\Domain\NoPendingCancelRequestException;
use App\Models\Task;
use App\Models\TaskCancelRequest;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;

/**
 * Pekerja MENJAWAB permintaan pembatalan dari popup di Detail Kerjaan.
 *
 * - Setuju (`approved`) → permintaan ditutup lalu `CancelTaskAction` yang
 *   membatalkan task-nya (dana kembali, lelang ditutup, status tercatat).
 *   Pembatalannya TETAP tercatat atas nama pemberi kerja — pekerja hanya
 *   menyetujui, bukan membatalkan.
 * - Tolak (`rejected`) → task jalan terus, antrean kosong lagi.
 *
 * Menjawab permintaan yang sudah dijawab/ditarik = `no_pending_cancel_request`
 * (klien memperlakukannya sebagai keadaan akhir, bukan galat).
 */
final class RespondTaskCancelAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly CancelTaskAction $cancel,
    ) {}

    public function approve(Task $task, TaskCancelRequest $request, User $worker): Task
    {
        return $this->db->transaction(function () use ($task, $request, $worker): Task {
            $this->claim($request);

            $request->forceFill([
                'status' => CancelRequestStatus::Approved,
                'decided_by' => $worker->getKey(),
                'decided_at' => now(),
            ])->save();

            // Penyetuju BUKAN pembatal: aktor pembatalan tetap peminta
            // (pemberi kerja) supaya `cancelled_by` dan penghitung
            // `cancellations` menunjuk orang yang benar.
            $requester = $request->requester()->firstOrFail();

            return $this->cancel->handle($task, $requester, $request->reason);
        });
    }

    public function reject(TaskCancelRequest $request, User $worker): TaskCancelRequest
    {
        return $this->db->transaction(function () use ($request, $worker): TaskCancelRequest {
            $this->claim($request);

            $request->forceFill([
                'status' => CancelRequestStatus::Rejected,
                'decided_by' => $worker->getKey(),
                'decided_at' => now(),
            ])->save();

            return $request->refresh();
        });
    }

    /**
     * Kunci barisnya dulu: dua jawaban yang datang bersamaan tidak boleh
     * dua-duanya lolos — yang kedua mendapat `no_pending_cancel_request`.
     */
    private function claim(TaskCancelRequest $request): void
    {
        $fresh = TaskCancelRequest::query()
            ->whereKey($request->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if ($fresh->status !== CancelRequestStatus::Pending) {
            throw new NoPendingCancelRequestException;
        }
    }
}
