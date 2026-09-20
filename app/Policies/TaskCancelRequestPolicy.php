<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\BidStatus;
use App\Models\TaskCancelRequest;
use App\Models\User;

/**
 * Otorisasi permintaan-persetujuan pembatalan.
 *
 * Aturan bisnis (sudah deal? masih pending?) dijaga Action — di sini hanya
 * SOAL SIAPA: meminta/menarik = peminta (poster), menjawab = pekerja yang
 * deal di task itu.
 */
final class TaskCancelRequestPolicy
{
    public function approve(User $user, TaskCancelRequest $request): bool
    {
        return $this->isDealtWorker($user, $request);
    }

    public function reject(User $user, TaskCancelRequest $request): bool
    {
        return $this->isDealtWorker($user, $request);
    }

    public function withdraw(User $user, TaskCancelRequest $request): bool
    {
        return $user->getKey() === $request->requested_by;
    }

    private function isDealtWorker(User $user, TaskCancelRequest $request): bool
    {
        return $request->task()->firstOrFail()
            ->bids()
            ->where('bidder_id', $user->getKey())
            ->where('status', BidStatus::Accepted)
            ->exists();
    }
}
