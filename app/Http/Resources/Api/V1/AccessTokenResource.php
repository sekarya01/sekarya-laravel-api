<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Laravel\Sanctum\NewAccessToken;

/**
 * Hasil tukar long_lived token. Long_lived token TIDAK dikembalikan lagi
 * karena tidak berubah — klien tetap memakai yang sudah dipegang.
 */
final class AccessTokenResource extends JsonResource
{
    public function __construct(private readonly NewAccessToken $token)
    {
        parent::__construct($token);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $expiresAt = $this->token->accessToken->expires_at;

        return [
            'token_type' => 'Bearer',
            'access_token' => $this->token->plainTextToken,
            'access_expires_at' => $expiresAt?->toIso8601String(),
            'access_expires_in_seconds' => $expiresAt === null
                ? null
                : max(0, (int) now()->diffInSeconds($expiresAt, false)),
        ];
    }
}
