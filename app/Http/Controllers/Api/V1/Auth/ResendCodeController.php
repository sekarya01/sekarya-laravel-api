<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ResendVerificationCodeAction;
use App\Data\Auth\ResendCodeData;
use App\Http\Requests\Api\V1\Auth\ResendCodeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class ResendCodeController
{
    public function __construct(private readonly ResendVerificationCodeAction $action) {}

    public function __invoke(ResendCodeRequest $request): JsonResponse
    {
        $this->action->handle(ResendCodeData::fromRequest($request), $request->ip());

        // Respons SELALU sama, terdaftar atau tidak — supaya endpoint ini
        // tidak bisa dipakai memeriksa email mana yang punya akun.
        return new JsonResponse([
            'message' => 'Jika email terdaftar dan belum terverifikasi, kode baru sudah dikirim.',
        ], Response::HTTP_ACCEPTED);
    }
}
