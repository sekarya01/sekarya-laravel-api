<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Harga referensi satu kategori di satu kota (U17). Ditulis
 * `RecomputeReferencePricesAction`, hanya dibaca lewat `GET categories?city=`.
 */
final class CategoryCityPrice extends Model
{
    /** Data turunan — tidak punya created_at/updated_at. */
    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = [
        'category_id', 'city',
        'ref_price_min', 'ref_price_max', 'ref_price_median',
        'ref_sample_size', 'ref_computed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category_id' => 'integer',
            'ref_price_min' => 'integer',
            'ref_price_max' => 'integer',
            'ref_price_median' => 'integer',
            'ref_sample_size' => 'integer',
            'ref_computed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
