<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\WalletTopupStatus;
use App\Models\User;
use App\Models\WalletTopup;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WalletTopup>
 */
final class WalletTopupFactory extends Factory
{
    protected $model = WalletTopup::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'user_id' => User::factory(),
            'amount' => fake()->numberBetween(10, 500) * 1000,
            'status' => WalletTopupStatus::AwaitingConfirmation,
            'sender_note' => 'BCA a.n. '.fake()->name(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (): array => [
            'status' => WalletTopupStatus::Confirmed,
            'reviewed_at' => now(),
            'confirmed_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => WalletTopupStatus::Rejected,
            'reviewed_at' => now(),
            'rejected_at' => now(),
            'rejection_reason' => 'Tidak ada mutasi masuk dengan nominal itu.',
        ]);
    }
}
