<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Requests\Api\V1\Auth\CheckAvailabilityRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Pra-cek ketersediaan email/username/phone untuk langkah 1 pendaftaran.
 * TIDAK membuat apa pun — validasi gagal otomatis 422 dengan `errors`
 * per field, sama seperti register.
 */
final class CheckAvailabilityController
{
    public function __invoke(CheckAvailabilityRequest $request): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Data tersedia untuk dipakai.',
            'data' => ['available' => true],
        ], Response::HTTP_OK);
    }
}
