<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Task;
use Illuminate\Http\Request;

/** @mixin Task */
final class TaskResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'task_number' => $this->task_number,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'options' => $this->options ?? [],
            'photos' => array_values($this->photos ?? []),
            'budget' => [
                'min' => $this->budget_min,
                // null berarti tidak ada batas atas — bukan 0, bukan tak diisi.
                'max' => $this->budget_max,
                'reference_median' => $this->ref_price_median,
            ],
            'location' => [
                'text' => $this->location_text,
                'city' => $this->city,
                'latitude' => $this->latitude === null ? null : (float) $this->latitude,
                'longitude' => $this->longitude === null ? null : (float) $this->longitude,
                'is_remote' => $this->is_remote,
            ],
            'bids_count' => $this->bids_count,

            // Perekrutan. `needed` sekaligus kuota pelamar: task 30 orang
            // menerima paling banyak 30 lamaran.
            'hiring' => [
                'workers_needed' => $this->workers_needed,
                'workers_hired' => $this->workers_hired,
                'slots_remaining' => $this->slotsRemaining(),
            ],

            // TOTAL yang disepakati untuk seluruh pekerja, bukan harga satu
            // orang. Harga per orang ada di `bids.amount` dan
            // `activities.agreed_amount`.
            'agreed_amount' => $this->agreed_amount,
            'needed_at' => $this->iso($this->needed_at),
            'bidding_closes_at' => $this->iso($this->bidding_closes_at),
            'dealt_at' => $this->iso($this->dealt_at),
            'completed_at' => $this->iso($this->completed_at),
            'cancelled_at' => $this->iso($this->cancelled_at),
            'cancelled_by' => $this->cancelled_by,
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
            // Hanya terisi kalau feed dipanggil dengan lat/lng.
            'distance_km' => $this->when(
                isset($this->distance_km),
                fn (): ?float => round((float) $this->distance_km, 2),
            ),
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'poster' => PublicUserResource::make($this->whenLoaded('poster')),
            // Terisi hanya di feed pencari kerja: null = belum dilamar.
            'my_bid' => BidResource::make($this->whenLoaded('myBid')),
            'workers' => PublicUserResource::collection($this->whenLoaded('workers')),
            'payment' => PaymentResource::make($this->whenLoaded('payment')),
            'activities' => ActivityResource::collection($this->whenLoaded('activities')),
            'created_at' => $this->iso($this->created_at),
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
