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
                // Dipantulkan kembali supaya klien bisa memastikan apa yang
                // tersimpan tanpa menunggu verifikasi email selesai — sebelum
                // itu tidak ada token, jadi `GET /me` belum bisa dipanggil.
                // `null` di sini berarti tidak diisi, bukan gagal disimpan.
                'gender' => $user->gender?->value,
                'city' => $user->city,
                'province' => $user->province,
                'code_expires_in_minutes' => (int) config('sekarya.verification.ttl_minutes'),
                'next_step' => 'POST /api/v1/auth/verify-email',
            ],
        ], Response::HTTP_ACCEPTED);
    }
}
