<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\CancelRequestStatus;
use App\Exceptions\Domain\NoPendingCancelRequestException;
use App\Models\TaskCancelRequest;
use Illuminate\Database\ConnectionInterface;

/**
 * Peminta MENARIK kembali permintaannya yang masih `pending`.
 *
 * Berubah pikiran bukan galat: task jalan terus, antrean kosong lagi, dan
 * peminta boleh meminta lagi nanti.
 */
final class WithdrawTaskCancelAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(TaskCancelRequest $request): TaskCancelRequest
    {
        return $this->db->transaction(function () use ($request): TaskCancelRequest {
            $fresh = TaskCancelRequest::query()
                ->whereKey($request->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($fresh->status !== CancelRequestStatus::Pending) {
                throw new NoPendingCancelRequestException;
            }

            $fresh->forceFill([
                'status' => CancelRequestStatus::Withdrawn,
                'withdrawn_at' => now(),
            ])->save();

            return $fresh->refresh();
        });
    }
}
