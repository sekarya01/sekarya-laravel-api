<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Data\Task\RaiseDisputeData;
use App\Enums\BidStatus;
use App\Enums\DisputeStatus;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\DisputeNotAllowedException;
use App\Models\Task;
use App\Models\TaskDispute;
use App\Models\User;

/**
 * Ajukan kendala atas tugas yang sedang `disputed` (G5).
 *
 * Hanya peserta task (pemberi kerja atau pekerja yang penawarannya diterima).
 * Satu tiket TERBUKA per task; pengajuan ulang memperbarui yang terbuka,
 * bukan menumpuk baris.
 */
final class RaiseDisputeAction
{
    public function handle(RaiseDisputeData $data, Task $task, User $user): TaskDispute
    {
        if ($task->status !== TaskStatus::Disputed) {
            throw DisputeNotAllowedException::becauseStatus($task->status->value);
        }

        $this->assertParticipant($task, $user);

        $existing = TaskDispute::query()
            ->where('task_id', $task->getKey())
            ->where('status', DisputeStatus::Open)
            ->first();

        if ($existing !== null) {
            $existing->fill([
                'raised_by' => $user->getKey(),
                'reason' => $data->reason,
                'evidence_photos' => $data->evidencePhotos === [] ? null : $data->evidencePhotos,
            ])->save();

            return $existing->refresh();
        }

        return TaskDispute::query()->create([
            'task_id' => $task->getKey(),
            'raised_by' => $user->getKey(),
            'reason' => $data->reason,
            'evidence_photos' => $data->evidencePhotos === [] ? null : $data->evidencePhotos,
            'status' => DisputeStatus::Open,
        ]);
    }

    private function assertParticipant(Task $task, User $user): void
    {
        if ((int) $task->poster_id === (int) $user->getKey()) {
            return;
        }

        $isAcceptedWorker = $task->bids()
            ->where('bidder_id', $user->getKey())
            ->where('status', BidStatus::Accepted)
            ->exists();

        if (! $isAcceptedWorker) {
            throw DisputeNotAllowedException::notParticipant();
        }
    }
}
