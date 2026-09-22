<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\DeviceToken;
use Illuminate\Http\Request;

/**
 * Token perangkat yang terdaftar.
 *
 * `token`-nya sendiri TIDAK dikembalikan: klien yang mendaftarkannya sudah
 * memegangnya, dan mengulanginya di respons hanya menambah satu tempat
 * rahasia perangkat bisa tersalin.
 *
 * @mixin DeviceToken
 */
final class DeviceTokenResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform->value,
            'last_used_at' => $this->iso($this->last_used_at),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
