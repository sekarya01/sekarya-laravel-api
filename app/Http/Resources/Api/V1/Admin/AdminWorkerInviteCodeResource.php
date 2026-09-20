<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\BaseResource;
use App\Models\WorkerInviteCode;
use Illuminate\Http\Request;

/**
 * Baris kode untuk antrean admin — tanpa hash. Hash sha256 keluar di sini
 * sama saja memberikan kunci brankas beserta alamatnya: siapa pun yang bisa
 * membaca antrean bisa redeem tanpa tahu kode aslinya.
 *
 * @mixin WorkerInviteCode
 */
final class AdminWorkerInviteCodeResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'prefix' => $this->resource->prefix,
            'max_uses' => $this->resource->max_uses,
            'used_count' => $this->resource->used_count,
            'remaining_uses' => $this->resource->remainingUses(),
            'expires_at' => $this->iso($this->resource->expires_at),
            'is_active' => $this->resource->is_active,
            'is_usable' => $this->resource->isUsable(),
            'note' => $this->resource->note,
            'created_at' => $this->iso($this->resource->created_at),
        ];
    }
}
