<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\TaskCancelRequest;
use Illuminate\Http\Request;

/** @mixin TaskCancelRequest */
final class TaskCancelRequestResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'task_id' => $this->task->ulid ?? null,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'requested_at' => $this->iso($this->created_at),
            'decided_at' => $this->iso($this->decided_at),
            'withdrawn_at' => $this->iso($this->withdrawn_at),
        ];
    }
}
