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
        // Agregat reputasi kini milik profil pekerja. `workerProfileOrNew()`
        // mengembalikan instance bernilai nol untuk orang yang belum pernah
        // bekerja, jadi bentuk responsnya sama untuk semua orang.
        $worker = $this->resource->workerProfileOrNew();

        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'gender' => $this->gender?->value,
            // Tanggal lahir keluar UTUH hanya di profil sendiri. Yang dilihat
            // orang lain cuma `age` — lihat PublicUserResource.
            'birth_date' => $this->birth_date?->toDateString(),
            'age' => $this->age,
            'phone' => $this->phone,
            'email' => $this->email,
            'phone_verified' => $this->phone_verified_at !== null,
            'avatar_url' => $this->publicUrl($this->avatar_path),
            'bio' => $this->bio,
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
            // Terdaftar sebagai pekerja. Sejajar dengan `identity_verified`
            // di profil publik, dan sama-sama DIHITUNG — profil pekerjanya
            // sendiri ada di GET /me/worker.
            'ready_to_work' => $this->resource->readyToWork(),
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
                'rating_avg' => (float) $worker->worker_rating_avg,
                'rating_count' => $worker->worker_rating_count,
                'tasks_completed' => $worker->tasks_completed,
                'bids_won' => $worker->bids_won,
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
