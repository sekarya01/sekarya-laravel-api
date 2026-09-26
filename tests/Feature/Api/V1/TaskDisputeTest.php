<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\TaskDispute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tiket kendala & resolusi admin (G5).
 */
final class TaskDisputeTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    private User $stranger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->poster = $this->activeUser();
        $this->stranger = $this->activeUser();
    }

    private function disputedTask(): Task
    {
        return Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Disputed,
        ]);
    }

    public function test_a_participant_can_raise_a_dispute(): void
    {
        $task = $this->disputedTask();

        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.disputes.store', $task->ulid), [
                'reason' => 'Hasil tidak sesuai kesepakatan.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');
    }

    public function test_a_stranger_cannot_raise_a_dispute(): void
    {
        $task = $this->disputedTask();

        $this->asUser($this->stranger)
            ->postJson(route('v1.tasks.disputes.store', $task->ulid), ['reason' => 'Coba-coba'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'dispute_not_allowed');
    }

    public function test_the_admin_can_resolve_a_dispute_with_a_refund(): void
    {
        $task = $this->disputedTask();

        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.disputes.store', $task->ulid), ['reason' => 'Hasil tidak sesuai'])
            ->assertCreated();

        $admin = $this->activeAdmin();

        $this->asAdmin($admin)
            ->getJson(route('v1.admin.disputes.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $disputeUlid = TaskDispute::query()->firstOrFail()->ulid;

        $this->asAdmin($admin)
            ->postJson(route('v1.admin.disputes.resolve', $disputeUlid), ['resolution' => 'refund'])
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved')
            ->assertJsonPath('data.resolution', 'refund');

        $this->assertSame(TaskStatus::Refunded, $task->refresh()->status);
    }
}
