<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

final class ActivityPolicy
{
    /** Yang mengerjakan: berangkat, mulai, kirim lokasi/kemajuan, serahkan hasil, tanggapi sengketa. */
    public function work(User $user, Activity $activity): bool
    {
        return $this->isWorker($user, $activity);
    }

    /** Yang memberi kerja: akui kedatangan, setujui hasil, atau ajukan sengketa. */
    public function judge(User $user, Activity $activity): bool
    {
        return $this->isPoster($user, $activity);
    }

    private function isWorker(User $user, Activity $activity): bool
    {
        return $user->getKey() === $activity->worker_id;
    }

    private function isPoster(User $user, Activity $activity): bool
    {
        return $user->getKey() === $activity->task->poster_id;
    }
}
