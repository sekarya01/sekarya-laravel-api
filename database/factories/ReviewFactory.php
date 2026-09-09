<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReviewerRole;
use App\Models\Review;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
final class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'task_id' => Task::factory(),
            'reviewer_id' => User::factory(),
            'reviewee_id' => User::factory(),
            'reviewer_role' => ReviewerRole::Poster,
            'rating' => fake()->numberBetween(4, 5),
            'comment' => fake()->sentence(),
            'is_visible' => true,
        ];
    }
}
