<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\ActorType;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Task;
use App\Models\TaskStatusLog;
use App\Support\TaskStatusRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TaskStatusRecorderTest extends TestCase
{
    use RefreshDatabase;

    private function task(TaskStatus $status = TaskStatus::Draft): Task
    {
        $this->seedReference();

        return Task::factory()->create([
            'poster_id' => $this->activeUser()->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => $status,
        ]);
    }

    public function test_it_moves_the_status_and_writes_a_log(): void
    {
        $task = $this->task();

        app(TaskStatusRecorder::class)->move($task, TaskStatus::Open, ActorType::Poster, 7, 'diterbitkan');

        $this->assertSame(TaskStatus::Open, $task->refresh()->status);

        $log = TaskStatusLog::query()->where('task_id', $task->getKey())->latest('id')->firstOrFail();
        $this->assertSame('draft', $log->from_status);
        $this->assertSame('open', $log->to_status);
        $this->assertSame(ActorType::Poster, $log->actor_type);
        $this->assertSame(7, (int) $log->actor_id);
        $this->assertSame('diterbitkan', $log->reason);
    }

    public function test_it_rejects_an_illegal_transition(): void
    {
        $task = $this->task(TaskStatus::Completed);

        try {
            app(TaskStatusRecorder::class)->move($task, TaskStatus::Open, ActorType::System);
            $this->fail('transisi terlarang seharusnya ditolak');
        } catch (InvalidStatusTransitionException $e) {
            $this->assertSame(['from' => 'completed', 'to' => 'open'], $e->context());
        }
    }

    public function test_an_illegal_transition_writes_no_log(): void
    {
        $task = $this->task(TaskStatus::Completed);

        try {
            app(TaskStatusRecorder::class)->move($task, TaskStatus::Open, ActorType::System);
        } catch (InvalidStatusTransitionException) {
            // diharapkan
        }

        $this->assertSame(0, TaskStatusLog::query()->where('task_id', $task->getKey())->count());
        $this->assertSame(TaskStatus::Completed, $task->refresh()->status);
    }

    public function test_metadata_is_stored_when_given(): void
    {
        $task = $this->task();

        app(TaskStatusRecorder::class)->move(
            $task, TaskStatus::Open, ActorType::System, null, null, ['lat' => -6.1, 'lng' => 106.8],
        );

        $log = TaskStatusLog::query()->where('task_id', $task->getKey())->latest('id')->firstOrFail();
        $this->assertSame(['lat' => -6.1, 'lng' => 106.8], $log->metadata);
    }

    public function test_metadata_is_null_when_empty(): void
    {
        $task = $this->task();

        app(TaskStatusRecorder::class)->move($task, TaskStatus::Open, ActorType::System);

        $log = TaskStatusLog::query()->where('task_id', $task->getKey())->latest('id')->firstOrFail();
        $this->assertNull($log->metadata);
    }

    /** Append-only: tabel jejak tidak punya updated_at. */
    public function test_the_log_has_no_updated_at(): void
    {
        $task = $this->task();
        app(TaskStatusRecorder::class)->move($task, TaskStatus::Open, ActorType::System);

        $log = TaskStatusLog::query()->latest('id')->firstOrFail();

        $this->assertNull($log::UPDATED_AT);
        $this->assertNotNull($log->created_at);
    }
}
