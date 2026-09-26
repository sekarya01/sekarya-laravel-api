<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Task;
use App\Models\User;
use App\Models\UserReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Report & blokir pengguna (G7) — lewat HTTP.
 */
final class UserReportBlockTest extends TestCase
{
    use RefreshDatabase;

    private User $viewer;

    private User $target;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->viewer = $this->activeUser();
        $this->target = $this->activeUser();
    }

    private function targetTask(): Task
    {
        return Task::factory()->open()->create([
            'poster_id' => $this->target->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
        ]);
    }

    public function test_a_user_can_report_another_user(): void
    {
        $this->asUser($this->viewer)
            ->postJson(route('v1.users.reports.store', $this->target->ulid), [
                'reason' => 'fraud',
                'note' => 'Tidak mengerjakan setelah dibayar.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.reason', 'fraud')
            ->assertJsonPath('data.status', 'open');
    }

    public function test_a_user_cannot_report_themselves(): void
    {
        $this->asUser($this->viewer)
            ->postJson(route('v1.users.reports.store', $this->viewer->ulid), ['reason' => 'spam'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'cannot_report_self');
    }

    public function test_blocking_hides_tasks_and_blocks_bids(): void
    {
        $task = $this->targetTask();

        // Sebelum blokir: task target terlihat di feed.
        $before = $this->asUser($this->viewer)->getJson(route('v1.tasks.index'))->assertOk()->json('data.*.id');
        $this->assertContains($task->ulid, $before);

        $this->asUser($this->viewer)
            ->putJson(route('v1.users.block.store', $this->target->ulid))
            ->assertNoContent();

        // Sesudah blokir: task target hilang dari feed viewer.
        $after = $this->asUser($this->viewer)->getJson(route('v1.tasks.index'))->assertOk()->json('data.*.id');
        $this->assertNotContains($task->ulid, $after);

        // Dan penawaran ke task-nya ditolak.
        $this->asUser($this->viewer)
            ->postJson(route('v1.tasks.bids.store', $task->ulid), ['amount' => (int) $task->budget_min])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'user_blocked');

        // Profil target tampil seperti tidak ada.
        $this->asUser($this->viewer)
            ->getJson(route('v1.users.show', $this->target->ulid))
            ->assertNotFound();

        // Lepas blokir mengembalikan semuanya.
        $this->asUser($this->viewer)
            ->deleteJson(route('v1.users.block.destroy', $this->target->ulid))
            ->assertNoContent();

        $restored = $this->asUser($this->viewer)->getJson(route('v1.tasks.index'))->assertOk()->json('data.*.id');
        $this->assertContains($task->ulid, $restored);
    }

    public function test_the_admin_can_list_and_review_reports(): void
    {
        $this->asUser($this->viewer)
            ->postJson(route('v1.users.reports.store', $this->target->ulid), ['reason' => 'abuse'])
            ->assertCreated();

        $admin = $this->activeAdmin();

        $reports = $this->asAdmin($admin)
            ->getJson(route('v1.admin.reports.index'))
            ->assertOk()
            ->json('data');

        $this->assertCount(1, $reports);

        $ulid = UserReport::query()->firstOrFail()->ulid;

        $this->asAdmin($admin)
            ->postJson(route('v1.admin.reports.review', $ulid))
            ->assertOk()
            ->assertJsonPath('data.status', 'reviewed');
    }
}
