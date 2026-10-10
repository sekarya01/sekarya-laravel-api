<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Actions\Activity\StartActivityAction;
use App\Actions\Activity\SubmitActivityAction;
use App\Actions\Bid\AcceptBidAction;
use App\Actions\Bid\PlaceBidAction;
use App\Data\Activity\SubmitActivityData;
use App\Data\Bid\PlaceBidData;
use App\Enums\ActivityStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Enums\WalletEntryType;
use App\Models\Activity;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use App\Models\WalletEntry;
use App\Support\Push\PushNotifier;
use App\Support\WalletLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakePushNotifier;
use Tests\TestCase;

/**
 * Persetujuan otomatis hasil yang tak kunjung dikonfirmasi: activity
 * `submitted` yang `submitted_at`-nya melewati tenggang disetujui sistem,
 * pekerjanya dibayar, task-nya selesai. Task `disputed` tidak disentuh.
 */
final class AutoApproveActivitiesTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    private User $worker;

    private Task $task;

    private Activity $activity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->poster = $this->activeUser();
        $this->fundWallet($this->poster, 1_000_000);
        $this->worker = $this->activeUser();
        $this->task = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Open,
            'budget_min' => 100_000,
            'workers_needed' => 1,
        ]);

        $bid = app(PlaceBidAction::class)->handle(
            new PlaceBidData(amount: 100_000, message: 'siap'),
            $this->task,
            $this->worker,
        );
        app(AcceptBidAction::class)->handle($bid, $this->poster);

        $activities = $this->openActivities($this->task->refresh(), $this->poster);
        $activity = $this->bringToSite($activities->sole());
        app(StartActivityAction::class)->handle($activity);
        $this->activity = app(SubmitActivityAction::class)
            ->handle(new SubmitActivityData('beres', ['p/a.jpg']), $activity->refresh());
    }

    public function test_it_auto_approves_stale_submission_and_pays_the_worker(): void
    {
        $push = new FakePushNotifier;
        $this->app->instance(PushNotifier::class, $push);

        $this->activity->forceFill(['submitted_at' => now()->subHours(25)])->save();

        $this->artisan('sekarya:activities:auto-approve')->assertSuccessful();

        $this->assertSame(ActivityStatus::Approved, $this->activity->refresh()->status);
        $this->assertSame(TaskStatus::Completed, $this->task->refresh()->status);

        $payment = Payment::query()->where('task_id', $this->task->getKey())->sole();
        $this->assertSame(PaymentStatus::Released, $payment->status);

        // Upah masuk saldo pekerja, dan ia dikabari.
        $this->assertSame(100_000, (int) app(WalletLedger::class)->walletFor($this->worker->refresh())->balance);
        $this->assertTrue($push->hasTypeTo($this->worker, 'activity_approved'));

        // Audit jujur: yang menyetujui tercatat sistem, bukan poster.
        $this->assertDatabaseHas('task_status_logs', [
            'task_id' => $this->task->getKey(),
            'to_status' => TaskStatus::Completed->value,
            'actor_type' => 'system',
        ]);
    }

    public function test_it_leaves_fresh_submission_untouched(): void
    {
        $this->artisan('sekarya:activities:auto-approve')->assertSuccessful();

        $this->assertSame(ActivityStatus::Submitted, $this->activity->refresh()->status);
        $this->assertSame(0, (int) app(WalletLedger::class)->walletFor($this->worker->refresh())->balance);
    }

    /**
     * Sengketa berlaku per mitra: mitra lain di task `disputed` yang hasilnya
     * didiamkan tetap disetujui otomatis; yang disengketakan tidak disentuh.
     */
    public function test_it_approves_the_other_worker_of_a_disputed_task(): void
    {
        $second = $this->activeUser();
        $task = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Open,
            'budget_min' => 100_000,
            'workers_needed' => 2,
        ]);

        $submitted = [];
        foreach ([$this->worker, $second] as $worker) {
            $bid = app(PlaceBidAction::class)->handle(
                new PlaceBidData(amount: 100_000, message: 'siap'),
                $task,
                $worker,
            );
            app(AcceptBidAction::class)->handle($bid, $this->poster);
        }

        foreach ($this->openActivities($task->refresh(), $this->poster) as $activity) {
            $activity = $this->bringToSite($activity);
            app(StartActivityAction::class)->handle($activity);
            $submitted[] = app(SubmitActivityAction::class)
                ->handle(new SubmitActivityData('beres', ['p/a.jpg']), $activity->refresh());
        }

        $this->raiseDispute($submitted[1]->refresh(), $this->poster, 'Kurang rapi di bagian dapur.');
        $this->assertSame(TaskStatus::Disputed, $task->refresh()->status);

        $submitted[0]->forceFill(['submitted_at' => now()->subHours(25)])->save();

        $this->artisan('sekarya:activities:auto-approve')->assertSuccessful();

        $this->assertSame(ActivityStatus::Approved, $submitted[0]->refresh()->status);
        $this->assertSame(ActivityStatus::Rejected, $submitted[1]->refresh()->status);
        $this->assertSame(TaskStatus::Disputed, $task->refresh()->status);
        $this->assertSame(
            PaymentStatus::Held,
            Payment::query()->where('task_id', $task->getKey())->sole()->status,
        );
    }

    public function test_it_is_idempotent(): void
    {
        $this->activity->forceFill(['submitted_at' => now()->subHours(25)])->save();

        $this->artisan('sekarya:activities:auto-approve')->assertSuccessful();
        $this->artisan('sekarya:activities:auto-approve')->assertSuccessful();

        // Dana keluar sekali: satu baris earning untuk pekerja ini.
        $walletId = app(WalletLedger::class)->walletFor($this->worker->refresh())->getKey();
        $this->assertSame(1, WalletEntry::query()
            ->where('wallet_id', $walletId)
            ->where('type', WalletEntryType::Earning->value)
            ->count());
    }
}
