<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WorkerInviteCode;
use App\Support\WorkerInviteCodeGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkerInviteCode>
 */
class WorkerInviteCodeFactory extends Factory
{
    protected $model = WorkerInviteCode::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $plain = WorkerInviteCodeGenerator::generate();

        return [
            'code_hash' => WorkerInviteCode::hash($plain),
            'code_plain' => $plain,
            'prefix' => mb_substr($plain, 0, 2),
            'max_uses' => 10,
            'used_count' => 0,
            'expires_at' => now()->addDays(30),
            'is_active' => true,
        ];
    }
}
