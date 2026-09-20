<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Task;

use App\Enums\CancelRequestStatus;
use App\Exceptions\Domain\NoPendingCancelRequestException;
use App\Http\Resources\Api\V1\TaskCancelRequestResource;
use App\Models\Task;

final class ShowTaskCancelRequestController
{
    /**
     * Permintaan `pending` milik task — inilah yang di-poll mobile untuk
     * memunculkan popup di Detail Kerjaan. Tanpa yang pending =
     * `no_pending_cancel_request` (keadaan akhir, bukan galat).
     */
    public function __invoke(Task $task): TaskCancelRequestResource
    {
        $cancelRequest = $task->cancelRequests()
            ->where('status', CancelRequestStatus::Pending)
            ->latest('id')
            ->first();

        if ($cancelRequest === null) {
            throw new NoPendingCancelRequestException;
        }

        return TaskCancelRequestResource::make($cancelRequest->load('task'));
    }
}
