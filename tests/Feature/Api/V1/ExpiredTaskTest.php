<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Support\Push\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePushNotifier;
use Tests\TestCase;

/**
 * Tugas yang jadwal mulainya lewat tanpa pekerja → `expired`, TANPA refund
 * otomatis. Pemberi kerja memilih: Ubah (jadwal baru → `open` lagi) atau
 * Batal (dana kembali ke saldo).
 */
final class ExpiredTaskTest extends TestCase
{
    protected bool $fundUsers = true;

    use RefreshDatabase;

    private User $poster;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->poster = $this->activeUser();
    }

    private function balance(): int
    {
        return (int) $this->poster->fresh()->walletOrNew()->balance;
    }

    /** Tugas dipasang lewat HTTP (dana ditahan), lalu jadwalnya dilewati. */
    private function expiredTask(): Task
    {
        $ulid = $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), [
                'category_id' => $this->anyCategory()->getKey(),
                'title' => 'Cuci AC 2 unit di rumah',
                'description' => 'Servis AC split, freon dan cuci evaporator.',
                'budget_min' => 150_000,
                'city' => 'Jakarta',
                'needed_at' => now()->addHour()->toIso8601String(),
                'end_at' => now()->addHours(3)->toIso8601String(),
                'publish_now' => true,
            ])
            ->assertCreated()
            ->json('data.id');

        $this->travel(2)->hours();
        $this->artisan('sekarya:tasks:expire-bidding')->assertSuccessful();

        return Task::query()->where('ulid', $ulid)->firstOrFail();
    }

    public function test_an_open_task_past_its_start_time_expires_without_a_refund(): void
    {
        $push = new FakePushNotifier;
        $this->app->instance(PushNotifier::class, $push);

        $before = $this->balance();
        $task = $this->expiredTask();

        $this->assertSame(TaskStatus::Expired, $task->status);
        // Dana tetap ditahan: tugasnya masih bisa dibuka lagi.
        $this->assertSame($before - 150_000, $this->balance());
        $this->assertTrue($push->hasTypeTo($this->poster, 'task_expired'));
    }

    public function test_a_task_that_already_hired_someone_does_not_expire_on_its_start_time(): void
    {
        $task = Task::factory()->open()->create([
            'poster_id' => $this->poster->getKey(),
            'needed_at' => now()->subMinute(),
            'workers_needed' => 3,
            'workers_hired' => 1,
        ]);

        $this->artisan('sekarya:tasks:expire-bidding')->assertSuccessful();

        $this->assertSame(TaskStatus::Open, $task->refresh()->status);
    }

    public function test_editing_an_expired_task_with_a_new_start_reopens_it(): void
    {
        $task = $this->expiredTask();

        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $task->ulid), [
                'needed_at' => now()->addDay()->toIso8601String(),
                'end_at' => now()->addDay()->addHours(2)->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'open');

        // Putaran penutup berikutnya tidak menutupnya lagi.
        $this->artisan('sekarya:tasks:expire-bidding')->assertSuccessful();
        $this->assertSame(TaskStatus::Open, $task->refresh()->status);
    }

    public function test_an_expired_task_cannot_be_reopened_without_a_new_start(): void
    {
        $task = $this->expiredTask();

        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $task->ulid), ['title' => 'Judul baru'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['needed_at']);

        $this->assertSame(TaskStatus::Expired, $task->refresh()->status);
    }

    public function test_a_new_start_after_the_stored_end_is_rejected(): void
    {
        $task = $this->expiredTask();

        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $task->ulid), [
                'needed_at' => now()->addDay()->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['end_at']);
    }

    public function test_cancelling_an_expired_task_refunds_the_held_funds(): void
    {
        $before = $this->balance();
        $task = $this->expiredTask();

        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.cancel', $task->ulid))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame($before, $this->balance());
    }
}
