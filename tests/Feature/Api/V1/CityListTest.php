<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Database\Seeders\CitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** `GET cities` — master kabupaten/kota (B12). */
final class CityListTest extends TestCase
{
    use RefreshDatabase;

    public function test_cities_are_public_and_searchable_by_prefix(): void
    {
        $this->seed(CitySeeder::class);

        // Tanpa token: pemilih kota dipakai juga di layar daftar.
        $all = $this->getJson(route('v1.cities.index'))->assertOk()->json('data');
        $this->assertNotEmpty($all);

        $filtered = $this->getJson(route('v1.cities.index', ['q' => 'Band']))
            ->assertOk()
            ->json('data');

        $names = array_column($filtered, 'name');
        $this->assertContains('Bandung', $names);
        $this->assertNotContains('Surabaya', $names);

        $bandung = collect($filtered)->firstWhere('name', 'Bandung');
        $this->assertSame('Jawa Barat', $bandung['province']);
    }

    public function test_the_limit_is_bounded(): void
    {
        $this->getJson(route('v1.cities.index', ['limit' => 500]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('limit');
    }
}
