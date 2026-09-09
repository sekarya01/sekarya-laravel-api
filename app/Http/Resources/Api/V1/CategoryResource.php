<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Category;
use Illuminate\Http\Request;

/** @mixin Category */
final class CategoryResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'icon' => $this->icon,
            'reference_price' => [
                'min' => $this->ref_price_min,
                'max' => $this->ref_price_max,
                'median' => $this->ref_price_median,
                'sample_size' => $this->ref_sample_size,
                // Kunci ini WAJIB dipakai UI: false berarti angka di atas masih
                // perkiraan manual, bukan data nyata. Menampilkan seed seolah
                // data nyata akan menyesatkan pemberi kerja sejak hari pertama.
                'from_real_data' => $this->hasRealPriceData(),
                'computed_at' => $this->iso($this->ref_computed_at),
            ],
        ];
    }
}
