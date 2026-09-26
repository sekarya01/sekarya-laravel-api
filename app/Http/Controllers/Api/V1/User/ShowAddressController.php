<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Resources\Api\V1\UserAddressResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ShowAddressController
{
    /**
     * Belum pernah disimpan = `{"data": null}` dengan 200, bukan 404: "belum
     * ada alamat" adalah keadaan sah yang ditanyakan setiap layar pengisian,
     * bukan galat. Membaca tidak membuat baris.
     */
    public function __invoke(Request $request): JsonResponse|UserAddressResource
    {
        $address = $request->user()->savedAddress()->first();

        return $address === null
            ? new JsonResponse(['data' => null])
            : UserAddressResource::make($address);
    }
}
