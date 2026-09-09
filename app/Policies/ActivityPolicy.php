<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

final class ActivityPolicy
{
    public function view(User $user, Activity $activity): bool
    {
        return $this->isWorker($user, $activity) || $this->isPoster($user, $activity);
    }

    /** Yang mengerjakan: mulai & serahkan hasil. */
    public function work(User $user, Activity $activity): bool
    {
        return $this->isWorker($user, $activity);
    }

    /** Yang memberi kerja: setujui atau tolak hasil. */
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
