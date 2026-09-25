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
            // Jarak pelamar ke lokasi task, km 1 desimal (U8). Terisi HANYA di
            // daftar penawaran milik pemberi kerja (ListBidsAction::forTask);
            // `null` di tempat lain, dan `null` bila salah satu koordinat
            // kosong. Koordinat pekerjanya sendiri tidak pernah keluar.
            'distance_km' => $this->resource->getAttributes()['distance_km'] ?? null,
            'bidder' => PublicUserResource::make($this->whenLoaded('bidder')),
            'task' => TaskResource::make($this->whenLoaded('task')),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
