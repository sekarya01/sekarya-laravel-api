<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\BaseResource;
use App\Models\WorkerInviteCode;
use Illuminate\Http\Request;

/**
 * Baris kode untuk pengelola — plain-nya ikut (`code`), hash-nya tidak.
 * Hash sha256 keluar di sini sama saja memberikan kunci brankas beserta
 * alamatnya. Plain boleh keluar karena endpoint ini khusus pengelola
 * (guard admin + jejak audit), sesuai kebutuhan: kode harus terlihat
 * terus-menerus untuk dibagikan ke calon mitra.
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
            // Kode apa adanya; baris lama (sebelum kolomnya ada) tampil
            // sebagai prefix bertopeng.
            'code' => $this->resource->displayCode(),
            'prefix' => $this->resource->prefix,
            'max_uses' => $this->resource->max_uses,
            'used_count' => $this->resource->used_count,
            'remaining_uses' => $this->resource->remainingUses(),
            'expires_at' => $this->iso($this->resource->expires_at),
            'is_active' => $this->resource->is_active,
            'is_usable' => $this->resource->isUsable(),
            'note' => $this->resource->note,
            'city' => $this->resource->city,
            'province' => $this->resource->province,
            'area_label' => $this->resource->areaLabel(),
            // Jumlah jejak redeem — dihitung dari tabelnya, bukan dari
            // `used_count`, supaya selisih keduanya (kalau pernah ada)
            // terlihat, bukan tertutup.
            'redemptions_count' => $this->whenCounted('redemptions'),
            'created_by' => $this->resource->relationLoaded('creator')
                ? $this->resource->creator?->email
                : null,
            'created_at' => $this->iso($this->resource->created_at),
        ];
    }
}
