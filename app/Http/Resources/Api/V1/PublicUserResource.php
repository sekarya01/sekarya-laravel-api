<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Orang lain — dilihat pemberi kerja saat menimbang penawaran.
 *
 * Batas pengungkapan: nomor HP, email, alamat, dan foto verifikasi TIDAK
 * pernah keluar dari sini. Yang keluar hanya bahan pertimbangan.
 *
 * @mixin User
 */
final class PublicUserResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'avatar_url' => $this->publicUrl($this->avatar_path),
            'bio' => $this->bio,
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
            'city' => $this->city,
            'province' => $this->province,
            // Badge terverifikasi: dari withCount di Action, atau dihitung.
            'identity_verified' => $this->identityVerified(),
            'as_worker' => [
                'rating_avg' => (float) $this->worker_rating_avg,
                'rating_count' => $this->worker_rating_count,
                'tasks_completed' => $this->tasks_completed,
            ],
            'as_poster' => [
                'rating_avg' => (float) $this->poster_rating_avg,
                'rating_count' => $this->poster_rating_count,
                'tasks_posted' => $this->tasks_posted,
            ],
            'member_since' => $this->iso($this->created_at),
        ];
    }

    private function identityVerified(): bool
    {
        // identity_verified_count datang dari withCount() kalau di-eager load;
        // kalau tidak, jatuh ke query. Menghindari N+1 tanpa memaksa.
        return isset($this->identity_verified_count)
            ? $this->identity_verified_count > 0
            : $this->resource->isIdentityVerified();
    }
}
