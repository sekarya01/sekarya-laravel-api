<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
final class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'task_id' => Task::factory(),
            'payer_id' => User::factory(),
            'status' => PaymentStatus::Pending,
            'amount' => fake()->numberBetween(50, 500) * 1000,
        ];
    }

    /** Dana sudah ditahan — inilah yang membuka activity. */
    public function held(): static
    {
        return $this->state(fn (): array => [
            'status' => PaymentStatus::Held,
            'paid_at' => now(),
            'held_at' => now(),
        ]);
    }
}
