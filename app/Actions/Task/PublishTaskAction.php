<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\ActorType;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Support\TaskEscrow;
use App\Support\TaskStatusRecorder;
use Illuminate\Database\ConnectionInterface;

final class PublishTaskAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TaskStatusRecorder $recorder,
        private readonly TaskEscrow $escrow,
    ) {}

    public function handle(Task $task, User $poster): Task
    {
        return $this->db->transaction(function () use ($task, $poster): Task {
            $this->recorder->move($task, TaskStatus::Open, ActorType::Poster, $poster->getKey());
            // Tayang = dibiayai. Lihat TaskEscrow.
            $this->escrow->start($task, 'tugas diterbitkan');

            return $task->refresh();
        });
    }
}
