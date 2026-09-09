<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ActorType;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Task;
use App\Models\TaskStatusLog;

/**
 * Satu-satunya jalan memindahkan status task: memvalidasi transisi,
 * menyimpan status baru, dan mencatat jejaknya — dalam satu tempat.
 *
 * Tanpa ini, setiap Action akan menulis jejak sendiri dan cepat ada yang lupa.
 */
final class TaskStatusRecorder
{
    /** @param array<string, mixed> $metadata */
    public function move(
        Task $task,
        TaskStatus $to,
        ActorType $actorType,
        ?int $actorId = null,
        ?string $reason = null,
        array $metadata = [],
    ): void {
        $from = $task->status;

        if (! $from->canTransitionTo($to)) {
            throw InvalidStatusTransitionException::between(
                $from->value,
                $to->value,
            );
        }

        $task->status = $to;
        $task->save();

        TaskStatusLog::query()->create([
            'task_id' => $task->getKey(),
            'from_status' => $from->value,
            'to_status' => $to->value,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'reason' => $reason,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
