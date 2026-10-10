<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\TaskDispute;
use Illuminate\Http\Request;

/**
 * Sengketa atas hasil satu mitra.
 *
 * @mixin TaskDispute
 */
final class TaskDisputeResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            // Mitra yang disengketakan (activity). NULL hanya untuk tiket lama.
            'activity_id' => $this->activity?->ulid,
            'category' => $this->category?->value,
            'reason' => $this->reason,
            'evidence_photos' => $this->evidence_photos ?? [],
            'worker_response' => $this->worker_response,
            'worker_evidence_photos' => $this->worker_evidence_photos ?? [],
            'worker_responded_at' => $this->iso($this->worker_responded_at),
            'status' => $this->status->value,
            'resolution' => $this->resolution?->value,
            'admin_note' => $this->admin_note,
            'task' => $this->whenLoaded('task', fn (): array => [
                'id' => $this->task->ulid,
                'title' => $this->task->title,
            ]),
            'created_at' => $this->iso($this->created_at),
            'resolved_at' => $this->iso($this->resolved_at),
        ];
    }
}
