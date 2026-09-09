<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use App\Enums\ActivityStatus;
use App\Enums\ActorType;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Activity;
use App\Models\User;
use App\Support\TaskStatusRecorder;
use Illuminate\Database\ConnectionInterface;

/**
 * Pemberi kerja menyetujui hasil → task selesai → DANA DILEPAS.
 *
 * Pelepasan di sini masih penanda status ([[payments]] stub). Pencairan nyata
 * ke rekening penerima kerja menyusul bersama riset pembayaran.
 */
final class ApproveActivityAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TaskStatusRecorder $recorder
    ) {}

    public function handle(Activity $activity, User $poster, ?string $note = null): Activity
    {
        return $this->db->transaction(function () use ($activity, $poster, $note): Activity {
            if (! $activity->status->canTransitionTo(ActivityStatus::Approved)) {
                throw InvalidStatusTransitionException::between(
                    $activity->status->value,
                    ActivityStatus::Approved->value,
                );
            }

            $now = now();

            $activity->forceFill([
                'status' => ActivityStatus::Approved,
                'approved_at' => $now,
                'poster_note' => $note,
            ])->save();

            // Agregat yang ditampilkan di kartu penawaran. Dinaikkan per
            // pekerja yang disetujui, bukan per task — orang ini memang sudah
            // menyelesaikan bagiannya.
            $activity->worker()->increment('tasks_completed');

            $task = $activity->task;

            // Dana dilepas SEKALI, saat pekerja terakhir disetujui.
            //
            // Pembayarannya satu untuk seluruh task, jadi melepas pada
            // persetujuan pertama akan mengeluarkan seluruh dana untuk satu
            // orang — dan pekerja lain, yang pekerjaannya sudah ada di dalam
            // tagihan yang sama, tidak akan pernah bisa disetujui karena
            // pembayarannya sudah released.
            if (! $task->everyWorkerIsApproved()) {
                return $activity;
            }

            $payment = $activity->payment;
            if (! $payment->status->canTransitionTo(PaymentStatus::Released)) {
                throw InvalidStatusTransitionException::between(
                    $payment->status->value,
                    PaymentStatus::Released->value,
                );
            }
            $payment->forceFill([
                'status' => PaymentStatus::Released,
                'released_at' => $now,
            ])->save();

            $task->forceFill(['completed_at' => $now])->save();

            $this->recorder->move(
                $task,
                TaskStatus::Completed,
                ActorType::Poster,
                $poster->getKey(),
                reason: 'seluruh hasil disetujui, dana dilepas',
            );

            return $activity;
        });
    }
}
