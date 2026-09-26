<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\City;
use Illuminate\Http\Request;

/** Satu kabupaten/kota pada pemilih kota (B12). @mixin City */
final class CityResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'province' => $this->province,
            'type' => $this->type,
        ];
    }
}
