<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Data\User\RegisterDeviceData;
use App\Models\DeviceToken;
use App\Models\User;

/**
 * Daftarkan (atau segarkan) token perangkat milik seseorang.
 *
 * Kunci pencariannya `token`, bukan `(user_id, token)`. Token FCM menempel
 * pada pemasangan aplikasi: saat ponsel yang sama dipakai akun lain, token
 * yang sama didaftarkan ulang. Memakai kunci `(user_id, token)` akan
 * meninggalkan baris lama atas nama akun sebelumnya — dan notifikasi akun itu
 * terus muncul di ponsel yang sekarang dipegang orang lain.
 */
final class RegisterDeviceTokenAction
{
    public function handle(RegisterDeviceData $data, User $user): DeviceToken
    {
        return DeviceToken::query()->updateOrCreate(
            ['token' => $data->token],
            [
                'user_id' => $user->getKey(),
                'platform' => $data->platform,
                'last_used_at' => now(),
            ],
        );
    }
}
