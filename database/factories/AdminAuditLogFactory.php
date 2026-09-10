<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdminAction;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAuditLog>
 */
final class AdminAuditLogFactory extends Factory
{
    protected $model = AdminAuditLog::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $action = AdminAction::VerificationApproved;

        return [
            'admin_id' => Admin::factory(),
            'action' => $action,
            // Diturunkan dari tindakannya, sama seperti di AdminAuditRecorder —
            // fixture yang slug-nya beda dari jalur nyata akan membuat test
            // penyaringan jejak lulus atas data yang tidak pernah ada.
            'subject_type' => $action->subjectType(),
            'subject_id' => fake()->numberBetween(1, 1000),
            'reason' => null,
            'ip' => fake()->ipv4(),
        ];
    }
}
