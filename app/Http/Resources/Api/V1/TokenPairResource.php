<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\NewAccessToken;

/**
 * Pasangan token setelah verifikasi atau login.
 *
 * Nilai token hanya bisa dibaca SEKALI, di sini — yang tersimpan di database
 * cuma hash-nya. Klien wajib menyimpan keduanya.
 */
final class TokenPairResource extends JsonResource
{
    /** @param array{user: User, access: NewAccessToken, long_lived: NewAccessToken} $pair */
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
            // Dipakai HANYA untuk memanggil POST /auth/refresh.
            'long_lived_token' => $this->pair['long_lived']->plainTextToken,
            'access_expires_at' => $this->pair['access']->accessToken->expires_at?->toIso8601String(),
            'long_lived_expires_at' => $this->pair['long_lived']->accessToken->expires_at?->toIso8601String(),
            'access_expires_in_seconds' => $this->secondsUntil(
                $this->pair['access']->accessToken->expires_at,
            ),
            'user' => UserResource::make($this->pair['user']->load('skills')),
        ];
    }

    private function secondsUntil(?CarbonInterface $at): ?int
    {
        return $at === null ? null : max(0, (int) now()->diffInSeconds($at, false));
    }
}
