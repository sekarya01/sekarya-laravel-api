<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\BaseResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Pengguna dilihat dari sisi pengelola.
 *
 * Lebih terbuka daripada PublicUserResource (email dan nomor HP ikut, karena
 * moderasi tanpa cara menghubungi orangnya tidak berguna), tapi tetap bukan
 * "seluruh baris": kata sandi dan seluruh isi tabel verifikasi tidak lewat
 * sini. Status verifikasi keluar sebagai boolean, bukan sebagai dokumennya.
 *
 * @mixin User
 */
final class AdminUserResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $worker = $this->resource->workerProfileOrNew();

        return [
            'id' => $this->ulid,
            'name' => $this->name,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'username' => $this->username,
            'gender' => $this->gender?->value,
            // Pengelola melihat tanggalnya, bukan hanya umurnya: verifikasi
            // identitas mencocokkan tanggal lahir dengan yang tertera di KTP,
            // dan umur saja tidak bisa dicocokkan dengan apa pun.
            'birth_date' => $this->birth_date?->toDateString(),
            'age' => $this->age,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status->value,
            'active_mode' => $this->active_mode->value,
            'email_verified' => $this->email_verified_at !== null,
            'identity_verified' => $this->resource->isIdentityVerified(),
            'ready_to_work' => $this->resource->readyToWork(),
            'city' => $this->city,
            'province' => $this->province,
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
            'last_active_at' => $this->iso($this->last_active_at),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
