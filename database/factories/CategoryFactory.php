<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
final class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => Str::slug($name),
            'name' => Str::title($name),
            'description' => fake()->sentence(),
            'ref_price_min' => 50_000,
            'ref_price_max' => 300_000,
            'ref_price_median' => 120_000,
            // 0 = masih nilai perkiraan, bukan data nyata.
            'ref_sample_size' => 0,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /** Harga referensi sudah dihitung dari task yang selesai. */
    public function withRealPriceData(int $sampleSize = 240): static
    {
        return $this->state(fn (): array => [
            'ref_sample_size' => $sampleSize,
            'ref_computed_at' => now(),
        ]);
    }
}
