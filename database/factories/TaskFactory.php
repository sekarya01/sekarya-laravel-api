<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Task>
 */
final class TaskFactory extends Factory
{
    protected $model = Task::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $min = fake()->numberBetween(50, 500) * 1000;

        return [
            'ulid' => (string) Str::ulid(),
            'task_number' => sprintf('TK-%s-%s', now()->format('ymd'), strtoupper(Str::random(6))),
            'poster_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => Str::ucfirst(fake()->words(4, true)),
            'description' => fake()->paragraph(),
            'budget_min' => $min,
            // Default punya batas atas; pakai state openBudget() untuk yang tanpa max.
            'budget_max' => $min * 2,
            'city' => fake()->city(),
            'status' => TaskStatus::Draft,
        ];
    }

    /** Batas atas tidak diisi — max itu opsional. */
    public function openBudget(): static
    {
        return $this->state(fn (): array => ['budget_max' => null]);
    }

    public function open(): static
    {
        return $this->state(fn (): array => ['status' => TaskStatus::Open]);
    }
}
