<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\TaskStatus;
use App\Models\Category;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** `PUT /tasks/{task}` — penyuntingan isi task oleh pemiliknya. */
final class UpdateTaskTest extends TestCase
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

    private function task(array $attributes = []): Task
    {
        return Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'needed_at' => now()->addDays(5),
            ...$attributes,
        ]);
    }

    // ── jalur bahagia ───────────────────────────────────────────────────────

    public function test_the_poster_edits_a_draft_and_the_row_really_changes(): void
    {
        $task = $this->task(['title' => 'Judul lama', 'budget_min' => 100_000]);

        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $task->ulid), [
                'title' => 'Cuci AC 3 unit',
                'budget_min' => 250_000,
            ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Cuci AC 3 unit')
            ->assertJsonPath('data.budget.min', 250_000);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->getKey(),
            'title' => 'Cuci AC 3 unit',
            'budget_min' => 250_000,
        ]);
    }

    public function test_an_open_task_is_still_editable(): void
    {
        $task = $this->task(['status' => TaskStatus::Open]);

        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $task->ulid), ['title' => 'Judul baru'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Judul baru');
    }

    public function test_fields_that_are_not_sent_are_left_alone(): void
    {
        $task = $this->task([
            'title' => 'Judul lama',
            'description' => 'Deskripsi lama',
            'city' => 'Bandung',
            'budget_max' => 900_000,
        ]);

        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $task->ulid), ['title' => 'Judul baru'])
            ->assertOk();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->getKey(),
            'title' => 'Judul baru',
            'description' => 'Deskripsi lama',
            'city' => 'Bandung',
            'budget_max' => 900_000,
        ]);
    }

    public function test_sending_null_clears_an_optional_field(): void
    {
        $task = $this->task(['budget_min' => 100_000, 'budget_max' => 400_000]);

        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $task->ulid), ['budget_max' => null])
            ->assertOk()
            ->assertJsonPath('data.budget.max', null);

        $this->assertNull($task->refresh()->budget_max);
    }

    public function test_changing_the_category_rewrites_the_reference_price_snapshot(): void
    {
        $other = Category::query()->active()
            ->whereKeyNot($this->anyCategory()->getKey())
            ->firstOrFail();

        $task = $this->task(['ref_price_median' => 1]);

        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $task->ulid), ['category_id' => $other->getKey()])
            ->assertOk()
            ->assertJsonPath('data.category.id', $other->getKey());

        $this->assertSame(
            (int) $other->ref_price_median,
            (int) $task->refresh()->ref_price_median,
        );
    }

    public function test_skills_are_replaced_wholesale(): void
    {
        $task = $this->task();
        $task->skills()->sync([$this->skill('cuci-ac')->getKey()]);

        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $task->ulid), ['skills' => []])
            ->assertOk()
            ->assertJsonPath('data.skills', []);

        $this->assertSame(0, $task->refresh()->skills()->count());
    }

    // ── perbandingan antar-ruas pada permintaan parsial ─────────────────────

    public function test_end_at_alone_is_compared_against_the_stored_start(): void
    {
        $task = $this->task(['needed_at' => now()->addDays(5)]);

        // Tanpa needed_at di badan permintaan. Ini yang dulu ditolak salah oleh
        // aturan `after:needed_at`.
        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $task->ulid), [
                'end_at' => now()->addDays(6)->toIso8601String(),
            ])
            ->assertOk();
    }

    public function test_end_at_before_the_stored_start_is_rejected(): void
    {
        $task = $this->task(['needed_at' => now()->addDays(5)]);

        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $task->ulid), [
                'end_at' => now()->addDays(2)->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('end_at');
    }

    public function test_budget_max_alone_is_compared_against_the_stored_minimum(): void
    {
        $task = $this->task(['budget_min' => 300_000, 'budget_max' => 600_000]);

        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $task->ulid), ['budget_max' => 100_000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('budget_max');
    }

    public function test_a_past_start_date_is_rejected(): void
    {
        $task = $this->task();

        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $task->ulid), [
                'needed_at' => now()->subDay()->toIso8601String(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('needed_at');
    }

    // ── penjaga ─────────────────────────────────────────────────────────────

    public function test_a_stranger_cannot_edit_someone_elses_task(): void
    {
        $task = $this->task(['title' => 'Judul lama']);

        $this->asUser($this->stranger)
            ->putJson(route('v1.tasks.update', $task->ulid), ['title' => 'Disabotase'])
            ->assertForbidden();

        $this->assertSame('Judul lama', $task->refresh()->title);
    }

    public function test_a_guest_cannot_edit_anything(): void
    {
        $task = $this->task();

        $this->putJson(route('v1.tasks.update', $task->ulid), ['title' => 'Disabotase'])
            ->assertUnauthorized();
    }

    #[DataProvider('lockedStatuses')]
    public function test_a_task_that_is_no_longer_a_draft_or_open_is_locked(TaskStatus $status): void
    {
        $task = $this->task(['status' => $status, 'title' => 'Judul lama']);

        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $task->ulid), ['title' => 'Judul baru'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'task_not_editable')
            ->assertJsonPath('context.status', $status->value);

        $this->assertSame('Judul lama', $task->refresh()->title);
    }

    /** @return array<string, array{TaskStatus}> */
    public static function lockedStatuses(): array
    {
        return [
            'dealt' => [TaskStatus::Dealt],
            'active' => [TaskStatus::Active],
            'submitted' => [TaskStatus::Submitted],
            'completed' => [TaskStatus::Completed],
            'cancelled' => [TaskStatus::Cancelled],
            'expired' => [TaskStatus::Expired],
        ];
    }

    public function test_workers_needed_cannot_drop_below_the_number_already_hired(): void
    {
        $task = $this->task([
            'status' => TaskStatus::Open,
            'workers_needed' => 5,
            'workers_hired' => 3,
        ]);

        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $task->ulid), ['workers_needed' => 2])
            ->assertStatus(422)
            ->assertJsonPath('code', 'workers_needed_below_hired')
            ->assertJsonPath('context.workers_hired', 3);

        $this->assertSame(5, (int) $task->refresh()->workers_needed);
    }

    public function test_workers_needed_may_equal_the_number_already_hired(): void
    {
        $task = $this->task([
            'status' => TaskStatus::Open,
            'workers_needed' => 5,
            'workers_hired' => 3,
        ]);

        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $task->ulid), ['workers_needed' => 3])
            ->assertOk();

        $this->assertSame(3, (int) $task->refresh()->workers_needed);
    }
}
