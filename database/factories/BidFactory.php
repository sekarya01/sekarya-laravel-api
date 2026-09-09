<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BidStatus;
use App\Models\Bid;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Bid>
 */
final class BidFactory extends Factory
{
    protected $model = Bid::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'task_id' => Task::factory()->open(),
            'bidder_id' => User::factory()->working(),
            'amount' => fake()->numberBetween(50, 500) * 1000,
            'message' => fake()->sentence(),
            'status' => BidStatus::Pending,
        ];
    }
}
