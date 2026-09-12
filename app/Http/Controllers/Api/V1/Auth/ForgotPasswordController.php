<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RequestPasswordResetLinkAction;
use App\Data\Auth\ForgotPasswordData;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Minta tautan reset kata sandi. Email yang tidak terdaftar dijawab 422
 * `email_not_registered` — eksplisit sesuai permintaan alur.
 */
final class ForgotPasswordController
{
    public function __construct(private readonly RequestPasswordResetLinkAction $action) {}

    public function __invoke(ForgotPasswordRequest $request): JsonResponse
    {
        $data = ForgotPasswordData::fromRequest($request);

        $this->action->handle($data);

        return new JsonResponse([
            'message' => 'Tautan reset dikirim ke email Anda bila terdaftar.',
            'data' => [
                'email' => $data->email,
                'reset_expires_in_minutes' => (int) config('auth.passwords.users.expire'),
                'next_step' => 'Buka tautan di email, isi kata sandi baru, tekan Perbarui Password.',
            ],
        ], Response::HTTP_ACCEPTED);
    }
}
