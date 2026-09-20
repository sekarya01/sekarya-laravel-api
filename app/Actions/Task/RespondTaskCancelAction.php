<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\CancelApprovalStatus;
use App\Enums\CancelRequestStatus;
use App\Exceptions\Domain\NoPendingCancelRequestException;
use App\Exceptions\Domain\NotCancelResponderException;
use App\Models\Task;
use App\Models\TaskCancelApproval;
use App\Models\TaskCancelRequest;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;

/**
 * Pekerja MENJAWAB permintaan pembatalan dari popup di Detail Kerjaan.
 *
 * Penghitungannya tidak simetris, dan itu disengaja:
 *
 * - SETUJU tidak memutuskan apa pun sendirian; ia hanya mengurangi yang
 *   ditunggu. Task baru dibatalkan saat suara setuju yang TERAKHIR masuk.
 *   Sebelumnya satu jawaban sudah cukup — artinya pekerja pertama yang
 *   menekan "Setuju" membatalkan pekerjaan orang lain yang belum ditanya.
 * - MENOLAK langsung menggugurkan seluruh permintaan. Satu orang yang masih
 *   mau mengerjakan sudah cukup untuk membuat task ini tetap ada, dan
 *   memaksanya berhenti bukan sesuatu yang bisa diputuskan mayoritas.
 *
 * Pembatalannya TETAP tercatat atas nama pemberi kerja — pekerja hanya
 * menyetujui, bukan membatalkan — supaya `cancelled_by` dan penghitung
 * `cancellations` menunjuk orang yang benar.
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
            $fresh = $this->claim($request);
            $this->vote($fresh, $worker, CancelApprovalStatus::Approved);

            // Dibaca ULANG dari database, bukan dari koleksi yang sudah ada di
            // memori: baris yang baru disimpan tidak muncul di relasi yang
            // dimuat sebelum penyimpanan, dan suara terakhir akan terbaca
            // sebagai "masih ada yang ditunggu" — permintaan yang sudah bulat
            // menggantung selamanya.
            $stillWaiting = $fresh->approvals()
                ->where('status', '!=', CancelApprovalStatus::Approved->value)
                ->exists();

            if ($stillWaiting) {
                return $task->refresh();
            }

            $fresh->forceFill([
                'status' => CancelRequestStatus::Approved,
                'decided_by' => $worker->getKey(),
                'decided_at' => now(),
            ])->save();

            // Penyetuju BUKAN pembatal: aktor pembatalan tetap peminta
            // (pemberi kerja).
            return $this->cancel->handle(
                $task,
                $fresh->requester()->firstOrFail(),
                $fresh->reason,
            );
        });
    }

    public function reject(TaskCancelRequest $request, User $worker): TaskCancelRequest
    {
        return $this->db->transaction(function () use ($request, $worker): TaskCancelRequest {
            $fresh = $this->claim($request);
            $this->vote($fresh, $worker, CancelApprovalStatus::Rejected);

            $fresh->forceFill([
                'status' => CancelRequestStatus::Rejected,
                'decided_by' => $worker->getKey(),
                'decided_at' => now(),
            ])->save();

            return $fresh->refresh();
        });
    }

    /**
     * Kunci barisnya dulu: dua jawaban yang datang bersamaan tidak boleh
     * dua-duanya lolos — yang kedua mendapat `no_pending_cancel_request`.
     */
    private function claim(TaskCancelRequest $request): TaskCancelRequest
    {
        $fresh = TaskCancelRequest::query()
            ->whereKey($request->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if ($fresh->status !== CancelRequestStatus::Pending) {
            throw new NoPendingCancelRequestException;
        }

        return $fresh;
    }

    /**
     * Catat suara orang ini.
     *
     * Barisnya sudah ada sejak permintaan dibuat; kalau tidak ada, orang itu
     * bukan salah satu yang dimintai persetujuan — pekerja yang diterima
     * SESUDAH permintaan lahir, atau orang luar yang menebak URL.
     */
    private function vote(TaskCancelRequest $request, User $worker, CancelApprovalStatus $status): void
    {
        /** @var TaskCancelApproval|null $own */
        $own = $request->approvals()
            ->where('worker_id', $worker->getKey())
            ->lockForUpdate()
            ->first();

        if ($own === null) {
            throw new NotCancelResponderException;
        }

        $own->forceFill([
            'status' => $status,
            'responded_at' => now(),
        ])->save();
    }
}
