<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ActivityStatus;
use App\Models\Activity;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Activity>
 */
final class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'task_id' => Task::factory(),
            'worker_id' => User::factory()->working(),
            // Selalu lewat pembayaran yang held: activity tidak boleh ada tanpa itu.
            'payment_id' => Payment::factory()->held(),
            'status' => ActivityStatus::Open,
            'agreed_amount' => fake()->numberBetween(50, 500) * 1000,
            'opened_at' => now(),
        ];
    }
}
