<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use App\Data\Activity\SubmitActivityData;
use App\Enums\ActivityStatus;
use App\Enums\ActorType;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Activity;
use App\Support\TaskStatusRecorder;
use Illuminate\Database\ConnectionInterface;

final class SubmitActivityAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TaskStatusRecorder $recorder
    ) {}

    public function handle(SubmitActivityData $data, Activity $activity): Activity
    {
        return $this->db->transaction(function () use ($data, $activity): Activity {
            if (! $activity->status->canTransitionTo(ActivityStatus::Submitted)) {
                throw InvalidStatusTransitionException::between(
                    $activity->status->value,
                    ActivityStatus::Submitted->value,
                );
            }

            $activity->forceFill([
                'status' => ActivityStatus::Submitted,
                'submitted_at' => now(),
                'worker_note' => $data->workerNote,
                // Bukti berfoto: dasar penyelesaian sengketa.
                'proof_photos' => $data->proofPhotos === [] ? null : $data->proofPhotos,
                'rejected_at' => null,
            ])->save();

            // Status TASK mengikuti seluruh pekerja, bukan yang paling cepat.
            // Pada task satu orang syarat ini langsung terpenuhi, sehingga
            // perilakunya persis sama seperti sebelumnya — termasuk penolakan
            // saat task sudah disputed.
            if ($activity->task->everyWorkerHasSubmitted()) {
                $this->recorder->move(
                    $activity->task,
                    TaskStatus::Submitted,
                    ActorType::Worker,
                    $activity->worker_id,
                );
            }

            return $activity;
        });
    }
}
