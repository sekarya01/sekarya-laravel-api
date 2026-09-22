<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\DeviceToken;
use App\Models\User;

/**
 * Lepaskan token perangkat saat pengguna keluar.
 *
 * Hanya baris MILIK pengguna ini yang dihapus. Pemeriksaan `user_id` bukan
 * formalitas: kalau ponsel sudah berpindah ke akun lain, token yang sama kini
 * dimiliki akun itu — dan logout akun lama tidak boleh mematikan notifikasi
 * pemilik barunya.
 *
 * Idempoten: token yang sudah tidak ada bukan galat.
 */
final class ForgetDeviceTokenAction
{
    public function handle(string $token, User $user): void
    {
        DeviceToken::query()
            ->where('token', $token)
            ->where('user_id', $user->getKey())
            ->delete();
    }
}
