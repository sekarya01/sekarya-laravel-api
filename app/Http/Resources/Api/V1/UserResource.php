<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Profil milik sendiri. Lebih terbuka daripada PublicUserResource.
 *
 * @mixin User
 */
final class UserResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'phone_verified' => $this->phone_verified_at !== null,
            'avatar_url' => $this->publicUrl($this->avatar_path),
            'bio' => $this->bio,
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
            'active_mode' => $this->active_mode->value,
            'status' => $this->status->value,
            'theme' => $this->theme,
            'domicile' => [
                'address_line' => $this->address_line,
                'city' => $this->city,
                'province' => $this->province,
                'postal_code' => $this->postal_code,
            ],
            'as_worker' => [
                'rating_avg' => (float) $this->worker_rating_avg,
                'rating_count' => $this->worker_rating_count,
                'tasks_completed' => $this->tasks_completed,
                'bids_won' => $this->bids_won,
            ],
            'as_poster' => [
                'rating_avg' => (float) $this->poster_rating_avg,
                'rating_count' => $this->poster_rating_count,
                'tasks_posted' => $this->tasks_posted,
            ],
            'cancellations' => $this->cancellations,
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
