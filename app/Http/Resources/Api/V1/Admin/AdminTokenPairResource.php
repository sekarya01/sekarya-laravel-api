<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Models\Admin;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\NewAccessToken;

/**
 * Pasangan token pengelola.
 *
 * Bentuknya sengaja mirip TokenPairResource tapi kelasnya terpisah: kuncinya
 * `admin`, bukan `user`, dan klien konsol pengelola tidak boleh menerima
 * bentuk yang bisa tertukar dengan sesi pengguna. Nilai tokennya hanya bisa
 * dibaca SEKALI, di sini.
 */
final class AdminTokenPairResource extends JsonResource
{
    /** @param array{admin: Admin, access: NewAccessToken, long_lived: NewAccessToken} $pair */
    public function __construct(private readonly array $pair)
    {
        parent::__construct($pair);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'token_type' => 'Bearer',
            'access_token' => $this->pair['access']->plainTextToken,
            // Dipakai HANYA untuk memanggil POST /admin/auth/refresh.
            'long_lived_token' => $this->pair['long_lived']->plainTextToken,
            'access_expires_at' => $this->pair['access']->accessToken->expires_at?->toIso8601String(),
            'long_lived_expires_at' => $this->pair['long_lived']->accessToken->expires_at?->toIso8601String(),
            'access_expires_in_seconds' => $this->secondsUntil(
                $this->pair['access']->accessToken->expires_at,
            ),
            'admin' => AdminResource::make($this->pair['admin']),
        ];
    }

    private function secondsUntil(?CarbonInterface $at): ?int
    {
        return $at === null ? null : max(0, (int) now()->diffInSeconds($at, false));
    }
}
