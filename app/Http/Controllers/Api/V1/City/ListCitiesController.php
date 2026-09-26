<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\City;

use App\Actions\City\ListCitiesAction;
use App\Http\Requests\Api\V1\City\ListCitiesRequest;
use App\Http\Resources\Api\V1\CityResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Master kabupaten/kota (B12). PUBLIK — pemilih kota dipakai juga di layar
 * daftar, sebelum pengguna punya token.
 */
final class ListCitiesController
{
    public function __construct(private readonly ListCitiesAction $action) {}

    public function __invoke(ListCitiesRequest $request): AnonymousResourceCollection
    {
        return CityResource::collection($this->action->handle(
            trim((string) $request->input('q', '')),
            $request->filled('limit') ? $request->integer('limit') : 50,
        ));
    }
}
