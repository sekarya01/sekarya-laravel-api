<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ActorType;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Task;
use App\Models\TaskStatusLog;
use App\Support\Chat\ChatRoomLifecycle;

/**
 * Satu-satunya jalan memindahkan status task: memvalidasi transisi,
 * menyimpan status baru, dan mencatat jejaknya — dalam satu tempat.
 *
 * Tanpa ini, setiap Action akan menulis jejak sendiri dan cepat ada yang lupa.
 */
final class TaskStatusRecorder
{
    public function __construct(private readonly ChatRoomLifecycle $chat) {}

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

        // Task berakhir = chat berakhir (baca saja). Di sini, bukan di tiap
        // Action pembatal/penyelesai: ada banyak jalur ke status akhir, dan
        // yang lupa akan meninggalkan room yang masih menerima pesan.
        if ($to->isFinal()) {
            $this->chat->expire($task, $to);
        }
    }
}
