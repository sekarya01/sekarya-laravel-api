<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use App\Data\Activity\RaiseActivityDisputeData;
use App\Enums\ActivityStatus;
use App\Enums\ActorType;
use App\Enums\DisputeStatus;
use App\Exceptions\Domain\DisputeNotAllowedException;
use App\Models\Activity;
use App\Models\TaskDispute;
use App\Models\User;
use App\Support\Push\PushDispatcher;
use App\Support\Push\PushMessages;
use App\Support\TaskSettlement;
use Illuminate\Database\ConnectionInterface;

/**
 * Pemberi kerja menyengketakan hasil SATU MITRA. Dana tetap ditahan; yang
 * memutuskan pengelola (ResolveDisputeAction).
 *
 * Satu panggilan, satu transaksi: activity `rejected` DAN tiketnya lahir
 * bersama. Dulu dua panggilan (tolak, lalu ajukan kendala) — kalau yang kedua
 * gagal, task terkunci `disputed` tanpa tiket: tak muncul di antrean
 * pengelola, dananya tertahan, dan tidak ada yang tahu.
 *
 * Mitra lain di task yang sama tidak tersentuh: mereka tetap bisa disetujui
 * dan dibayar (TaskSettlement).
 */
final class RaiseActivityDisputeAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TaskSettlement $settlement,
        private readonly PushDispatcher $push,
    ) {}

    public function handle(RaiseActivityDisputeData $data, Activity $activity, User $poster): TaskDispute
    {
        $dispute = $this->db->transaction(function () use ($data, $activity, $poster): TaskDispute {
            $task = $this->settlement->lockTask($activity->task);
            $activity = Activity::query()->whereKey($activity->getKey())->lockForUpdate()->firstOrFail();

            if ($activity->status !== ActivityStatus::Submitted) {
                throw DisputeNotAllowedException::becauseStatus($activity->status->value);
            }

            $activity->forceFill([
                'status' => ActivityStatus::Rejected,
                'rejected_at' => now(),
            ])->save();

            $dispute = new TaskDispute;
            $dispute->forceFill([
                'task_id' => $task->getKey(),
                'activity_id' => $activity->getKey(),
                'raised_by' => $poster->getKey(),
                'category' => $data->category,
                'reason' => $data->reason,
                'evidence_photos' => $data->evidencePhotos === [] ? null : $data->evidencePhotos,
                'status' => DisputeStatus::Open,
            ])->save();

            $this->settlement->sync($task, ActorType::Poster, (int) $poster->getKey(), 'hasil disengketakan');

            return $dispute;
        });

        // Di LUAR transaksi: mitra diberi tahu, supaya bisa menanggapi.
        $this->push->send(
            $activity->worker_id,
            PushMessages::activityRejected($activity->task, $activity->refresh(), $data->category),
        );

        return $dispute;
    }
}
