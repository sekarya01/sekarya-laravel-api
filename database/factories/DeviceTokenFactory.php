<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DevicePlatform;
use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DeviceToken>
 */
final class DeviceTokenFactory extends Factory
{
    protected $model = DeviceToken::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            // Bentuk menyerupai token FCM sungguhan: satu blok panjang tanpa
            // spasi. Panjangnya sengaja di bawah batas kolom.
            'token' => 'fcm-'.Str::random(120),
            'platform' => DevicePlatform::Android,
            'last_used_at' => now(),
        ];
    }
}
