<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use App\Enums\ActivityStatus;
use App\Enums\ActorType;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Activity;
use App\Models\User;
use App\Support\TaskStatusRecorder;
use Illuminate\Database\ConnectionInterface;

/**
 * Pemberi kerja menolak hasil → sengketa. Dana TETAP ditahan.
 * Penyelesaiannya (admin memutuskan lepas atau kembalikan) menyusul.
 */
final class RejectActivityAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TaskStatusRecorder $recorder
    ) {}

    public function handle(Activity $activity, User $poster, ?string $note = null): Activity
    {
        return $this->db->transaction(function () use ($activity, $poster, $note): Activity {
            if (! $activity->status->canTransitionTo(ActivityStatus::Rejected)) {
                throw InvalidStatusTransitionException::between(
                    $activity->status->value,
                    ActivityStatus::Rejected->value,
                );
            }

            $activity->forceFill([
                'status' => ActivityStatus::Rejected,
                'rejected_at' => now(),
                'poster_note' => $note,
            ])->save();

            // Sengketa di tingkat TASK hanya kalau statusnya memang bisa ke
            // sana. Pada task banyak pekerja, satu orang bisa ditolak ketika
            // yang lain masih bekerja dan task-nya masih `active` — penolakan
            // itu tercatat pada activity-nya, dan tidak boleh menggagalkan
            // seluruh permintaan hanya karena transisi task tidak berlaku.
            // Pada task satu orang, status selalu `submitted` di titik ini,
            // jadi perilakunya tidak berubah.
            if ($activity->task->status->canTransitionTo(TaskStatus::Disputed)) {
                $this->recorder->move(
                    $activity->task,
                    TaskStatus::Disputed,
                    ActorType::Poster,
                    $poster->getKey(),
                    reason: $note,
                );
            }

            return $activity;
        });
    }
}
