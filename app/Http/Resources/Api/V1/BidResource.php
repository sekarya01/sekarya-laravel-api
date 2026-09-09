<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Bid;
use Illuminate\Http\Request;

/** @mixin Bid */
final class BidResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'amount' => $this->amount,
            'message' => $this->message,
            'option_responses' => $this->option_responses ?? [],
            'estimated_hours' => $this->estimated_hours === null
                ? null
                : (float) $this->estimated_hours,
            'can_start_at' => $this->iso($this->can_start_at),
            'status' => $this->status->value,
            'responded_at' => $this->iso($this->responded_at),
            // Bahan pertimbangan pemberi kerja: rating, jumlah kerja, verifikasi.
            'bidder' => PublicUserResource::make($this->whenLoaded('bidder')),
            'task' => TaskResource::make($this->whenLoaded('task')),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
