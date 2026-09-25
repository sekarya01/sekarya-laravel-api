<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Data\User\UpsertWorkerProfileData;
use App\Exceptions\Domain\NotAWorkerException;
use App\Exceptions\Domain\ProfileIncompleteException;
use App\Models\User;
use App\Models\UserWorker;
use Illuminate\Database\ConnectionInterface;

/**
 * Buat atau ubah profil pekerja milik seseorang.
 *
 * Menolak kalau identitas akunnya belum lengkap. Jenis kelamin dan tanggal
 * lahir tidak diterima di sini — keduanya hanya punya satu jalur tulis,
 * `PATCH /me` — jadi yang bisa dilakukan Action ini adalah menutup pintunya
 * dengan galat yang menyebut persis apa yang kurang.
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
        // Diperiksa DI LUAR transaksi: tidak ada yang perlu dibatalkan, dan
        // penolakan ini tidak bergantung pada apa pun yang dikunci di dalam.
        if (! $user->hasCompleteIdentity()) {
            throw ProfileIncompleteException::forWorkerProfile(array_values(array_filter([
                $user->gender === null ? 'gender' : null,
                $user->birth_date === null ? 'birth_date' : null,
            ])));
        }

        // Ketersediaan hanya berarti bagi yang SUDAH pekerja. Tanpa penjaga
        // ini, `{is_available: false}` dari akun pemberi kerja akan melahirkan
        // baris `user_workers` — dan orang itu tiba-tiba muncul di
        // `GET workers` hanya karena menekan sakelar.
        if ($data->changesAvailability() && ! $user->hasWorkerProfile()) {
            throw new NotAWorkerException;
        }

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
