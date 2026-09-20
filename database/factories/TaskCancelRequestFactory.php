<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CancelRequestStatus;
use App\Models\Task;
use App\Models\TaskCancelRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TaskCancelRequest>
 */
final class TaskCancelRequestFactory extends Factory
{
    protected $model = TaskCancelRequest::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'ulid' => (string) Str::ulid(),
            'task_id' => Task::factory(),
            'requested_by' => User::factory(),
            'reason' => fake()->sentence(),
            'status' => CancelRequestStatus::Pending,
        ];
    }
}
