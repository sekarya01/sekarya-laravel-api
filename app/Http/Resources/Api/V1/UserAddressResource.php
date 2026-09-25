<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\UserAddress;
use Illuminate\Http\Request;

/**
 * Alamat tersimpan — HANYA untuk pemiliknya (`me/address`). Tidak pernah
 * disematkan di Resource lain; alamat jalan dan koordinat rumah seseorang
 * bukan bahan pertimbangan siapa pun.
 *
 * @mixin UserAddress
 */
final class UserAddressResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'label' => $this->label,
            'address_line' => $this->address_line,
            'city' => $this->city,
            'province' => $this->province,
            'latitude' => $this->latitude === null ? null : (float) $this->latitude,
            'longitude' => $this->longitude === null ? null : (float) $this->longitude,
            'updated_at' => $this->iso($this->updated_at),
        ];
    }
}
