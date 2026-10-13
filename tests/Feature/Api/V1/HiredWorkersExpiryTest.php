<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BidStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use App\Support\Push\PushNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePushNotifier;
use Tests\TestCase;

/**
 * Tenggat lewat padahal sebagian pekerja sudah diterima (keputusan pemilik
 * 2026-10-13): pemberi kerja diperingatkan sekali 1 jam sebelumnya; tanpa
 * dimulai, task kedaluwarsa, pekerjanya dibatalkan + diberi tahu, dan seluruh
 * dana kembali ke saldo. Tanpa catatan pembatalan bagi siapa pun.
 */
final class HiredWorkersExpiryTest extends TestCase
{
    protected bool $fundUsers = true;

    use RefreshDatabase;

    private User $poster;

    private User $worker;

    private FakePushNotifier $push;

    private int $balanceBefore;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->push = new FakePushNotifier;
        $this->app->instance(PushNotifier::class, $this->push);

        $this->poster = $this->activeUser();
        $this->worker = $this->activeUser();
        $this->balanceBefore = $this->balance();

        // 3 orang × Rp150rb ditahan; satu diterima, dua slot masih kosong.
        $ulid = $this->asUser($this->poster)
            ->postJson(route('v1.tasks.store'), [
                'category_id' => $this->anyCategory()->getKey(),
                'title' => 'Bersih rumah tiga orang',
                'description' => 'Bersih menyeluruh rumah dua lantai.',
                'budget_min' => 150_000,
                'city' => 'Jakarta',
                'workers_needed' => 3,
                'needed_at' => now()->addHours(3)->toIso8601String(),
                'end_at' => now()->addHours(5)->toIso8601String(),
                'publish_now' => true,
            ])
            ->assertCreated()
            ->json('data.id');
        $this->task = Task::query()->where('ulid', $ulid)->firstOrFail();
        $this->hireWorker($this->task, $this->worker, 150_000);
    }

    private function balance(): int
    {
        return (int) $this->poster->fresh()->walletOrNew()->balance;
    }

    private function sweep(): void
    {
        $this->artisan('sekarya:tasks:expire-bidding')->assertSuccessful();
    }

    public function test_the_poster_is_warned_once_an_hour_before_the_deadline(): void
    {
        $this->travel(90)->minutes();
        $this->sweep();
        $this->assertFalse($this->push->hasTypeTo($this->poster, 'task_expiring'), 'terlalu dini');

        $this->travel(45)->minutes();
        $this->sweep();
        $this->sweep();

        $warnings = array_filter(
            $this->push->sent,
            fn (array $s): bool => $s['user']->is($this->poster) && $s['message']->data['type'] === 'task_expiring',
        );
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('1 dari 3 pekerja', reset($warnings)['message']->body);
        $this->assertSame(TaskStatus::Open, $this->task->refresh()->status);
    }

    public function test_past_the_deadline_hired_workers_are_dropped_and_the_funds_return(): void
    {
        $cancellations = [(int) $this->poster->fresh()->cancellations, (int) $this->worker->fresh()->cancellations];

        $this->travel(4)->hours();
        $this->sweep();

        $task = $this->task->refresh();
        $this->assertSame(TaskStatus::Expired, $task->status);
        $this->assertSame(0, (int) $task->workers_hired);
        $this->assertSame(BidStatus::Expired, $task->bids()->firstOrFail()->status);
        $this->assertTrue($this->push->hasTypeTo($this->worker, 'bid_expired'));
        $this->assertTrue($this->push->hasTypeTo($this->poster, 'task_expired'));

        // Seluruh dana kembali; tagihan tetap `held` bernominal 0.
        $this->assertSame($this->balanceBefore, $this->balance());
        $payment = $task->payment()->firstOrFail();
        $this->assertSame(PaymentStatus::Held, $payment->status);
        $this->assertSame(0, (int) $payment->amount);

        $this->assertSame($cancellations, [(int) $this->poster->fresh()->cancellations, (int) $this->worker->fresh()->cancellations]);
    }

    public function test_reopening_after_the_drop_holds_the_funds_again(): void
    {
        $this->travel(4)->hours();
        $this->sweep();

        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $this->task->ulid), [
                'needed_at' => now()->addDay()->toIso8601String(),
                'end_at' => now()->addDay()->addHours(2)->toIso8601String(),
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'open');

        $this->assertSame($this->balanceBefore - 450_000, $this->balance());
    }

    public function test_a_new_deadline_earns_a_new_warning(): void
    {
        $this->travel(150)->minutes();
        $this->sweep();
        $this->assertNotNull($this->task->refresh()->expiry_warned_at);

        $this->asUser($this->poster)
            ->putJson(route('v1.tasks.update', $this->task->ulid), [
                'needed_at' => now()->addDay()->toIso8601String(),
                'end_at' => now()->addDay()->addHours(2)->toIso8601String(),
            ])
            ->assertOk();

        $this->assertNull($this->task->refresh()->expiry_warned_at);
    }
}
