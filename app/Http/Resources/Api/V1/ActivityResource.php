<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Activity;
use Illuminate\Http\Request;

/** @mixin Activity */
final class ActivityResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'status' => $this->status->value,
            'agreed_amount' => $this->agreed_amount,
            'opened_at' => $this->iso($this->opened_at),
            'started_at' => $this->iso($this->started_at),
            'submitted_at' => $this->iso($this->submitted_at),
            'approved_at' => $this->iso($this->approved_at),
            'rejected_at' => $this->iso($this->rejected_at),
            'worker_note' => $this->worker_note,
            'proof_photos' => array_values($this->proof_photos ?? []),
            'poster_note' => $this->poster_note,
            'task' => TaskResource::make($this->whenLoaded('task')),
            'worker' => PublicUserResource::make($this->whenLoaded('worker')),
            'payment' => PaymentResource::make($this->whenLoaded('payment')),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
