<?php

declare(strict_types=1);

namespace App\Actions\Category;

use App\Models\Category;
use App\Models\CategoryCityPrice;
use Illuminate\Database\Eloquent\Collection;

/**
 * Katalog kategori + acuan harga.
 *
 * Bila `city` diberikan (U17), harga diisi dari acuan KOTA itu ketika sampelnya
 * cukup; kategori tanpa baris kota jatuh ke angka nasional. `scope` di Resource
 * memberi tahu klien mana yang sedang dipakai.
 */
final class ListCategoriesAction
{
    /** @return Collection<int, Category> */
    public function handle(?string $city = null): Collection
    {
        $categories = Category::query()->active()->get();

        if ($city === null || $city === '') {
            return $categories;
        }

        $prices = CategoryCityPrice::query()
            ->where('city', $city)
            ->get()
            ->keyBy('category_id');

        foreach ($categories as $category) {
            $price = $prices->get($category->getKey());

            if ($price === null) {
                $category->setAttribute('price_scope', 'national');

                continue;
            }

            $category->forceFill([
                'ref_price_min' => $price->ref_price_min,
                'ref_price_max' => $price->ref_price_max,
                'ref_price_median' => $price->ref_price_median,
                'ref_sample_size' => $price->ref_sample_size,
                'ref_computed_at' => $price->ref_computed_at,
            ]);

            $category->setAttribute('price_scope', 'city');
            $category->setAttribute('price_city', $city);
        }

        return $categories;
    }
}
