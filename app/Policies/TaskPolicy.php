<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\BidStatus;
use App\Models\Task;
use App\Models\User;

/**
 * Otorisasi ditentukan oleh peran seseorang PADA TASK ITU, bukan oleh
 * users.active_mode — active_mode hanya state tampilan.
 *
 * "Pekerja pada task ini" dibaca dari penawaran yang diterima, bukan dari
 * sebuah kolom di `tasks`: satu task bisa merekrut banyak orang.
 */
final class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $this->isPoster($user, $task)
            || $this->isWorker($user, $task)
            || $task->status->acceptsBids();
    }

    public function update(User $user, Task $task): bool
    {
        return $this->isPoster($user, $task);
    }

    public function manageBids(User $user, Task $task): bool
    {
        return $this->isPoster($user, $task);
    }

    public function pay(User $user, Task $task): bool
    {
        return $this->isPoster($user, $task);
    }

    public function cancel(User $user, Task $task): bool
    {
        return $this->isPoster($user, $task) || $this->isWorker($user, $task);
    }

    public function review(User $user, Task $task): bool
    {
        return $this->isPoster($user, $task) || $this->isWorker($user, $task);
    }

    private function isPoster(User $user, Task $task): bool
    {
        return $user->getKey() === $task->poster_id;
    }

    private function isWorker(User $user, Task $task): bool
    {
        return $task->bids()
            ->where('bidder_id', $user->getKey())
            ->where('status', BidStatus::Accepted)
            ->exists();
    }
}
