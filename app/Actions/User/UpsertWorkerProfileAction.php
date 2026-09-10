<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Data\User\UpsertWorkerProfileData;
use App\Models\User;
use App\Models\UserWorker;
use Illuminate\Database\ConnectionInterface;

/**
 * Buat atau ubah profil pekerja milik seseorang.
 *
 * Satu Action untuk dua hal, karena dari sisi pemanggil memang satu hal:
 * "beginilah profil pekerja saya". Endpoint-nya PUT, dan tidak ada endpoint
 * pembuatan terpisah — kalau ada, klien harus tahu lebih dulu apakah profilnya
 * sudah pernah dibuat untuk memilih endpoint yang benar, padahal jawabannya
 * ada di server.
 */
final class UpsertWorkerProfileAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(UpsertWorkerProfileData $data, User $user): UserWorker
    {
        return $this->db->transaction(function () use ($data, $user): UserWorker {
            $profile = $user->workerProfileOrCreate();

            $attributes = $data->toAttributes();

            if ($attributes !== []) {
                $profile->fill($attributes)->save();
            }

            // Relasi dipasang, bukan di-load ulang: pemiliknya sudah ada di
            // tangan. Resource membutuhkannya untuk meresolusi field yang
            // diwarisi, dan tanpa ini setiap penyimpanan profil menambah satu
            // kueri yang jawabannya sudah diketahui.
            $profile->setRelation('user', $user);

            return $profile;
        });
    }
}
