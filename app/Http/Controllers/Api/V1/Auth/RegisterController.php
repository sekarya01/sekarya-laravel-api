<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\RegisterUserAction;
use App\Data\Auth\RegisterData;
use App\Http\Requests\Api\V1\Auth\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Daftar akun. TIDAK mengembalikan token — akun belum aktif.
 */
final class RegisterController
{
    public function __construct(private readonly RegisterUserAction $action) {}

    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = $this->action->handle(RegisterData::fromRequest($request), $request->ip());

        return new JsonResponse([
            'message' => 'Akun dibuat. Kode verifikasi dikirim ke email Anda.',
            'data' => [
                'email' => $user->email,
                'status' => $user->status->value,
                'code_expires_in_minutes' => (int) config('sekarya.verification.ttl_minutes'),
                'next_step' => 'POST /api/v1/auth/verify-email',
            ],
        ], Response::HTTP_ACCEPTED);
    }
}
