<?php

declare(strict_types=1);

namespace App\Actions\City;

use App\Models\City;
use Illuminate\Database\Eloquent\Collection;

/**
 * Pemilih kota (B12): cari kabupaten/kota menurut awalan namanya.
 *
 * Pencariannya RENTANG `name >= 'q' AND name < 'q…'`, bukan `LIKE 'q%'`:
 * keduanya sama-sama memakai indeks, tapi rentang tidak perlu meng-escape `%`
 * dan `_` dari ketikan pengguna. Collation kolomnya case-insensitive, jadi
 * huruf besar/kecil tidak berpengaruh.
 */
final class ListCitiesAction
{
    /** @return Collection<int, City> */
    public function handle(string $q, int $limit): Collection
    {
        return City::query()
            ->when($q !== '', fn ($query) => $query
                ->where('name', '>=', $q)
                ->where('name', '<', $q."\u{10FFFF}"))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get();
    }
}
