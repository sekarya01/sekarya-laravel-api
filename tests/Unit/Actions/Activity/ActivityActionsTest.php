<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Activity;

use App\Actions\Activity\ApproveActivityAction;
use App\Actions\Activity\ListActivitiesAction;
use App\Actions\Activity\RejectActivityAction;
use App\Actions\Activity\StartActivityAction;
use App\Actions\Activity\SubmitActivityAction;
use App\Data\Activity\SubmitActivityData;
use App\Data\CursorPageData;
use App\Enums\ActivityStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Exceptions\Domain\PaymentNotHeldException;
use App\Models\Activity;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ActivityActionsTest extends TestCase
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
        $this->worker = $this->activeUser();
        $this->task = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Dealt,
        ]);
        $this->hireWorker($this->task, $this->worker, 220_000);
        Payment::factory()->create([
            'task_id' => $this->task->getKey(),
            'payer_id' => $this->poster->getKey(),
            'amount' => 220_000,
        ]);

        // Satu transfer membuka satu activity PER pekerja; task ini punya satu.
        $this->activity = $this->openActivities($this->task, $this->poster)->sole();
    }

    public function test_start_moves_to_in_progress(): void
    {
        $started = app(StartActivityAction::class)->handle($this->activity);

        $this->assertSame(ActivityStatus::InProgress, $started->status);
        $this->assertNotNull($started->started_at);
    }

    /** Dana bisa sudah dikembalikan sejak activity dibuka. */
    public function test_start_rechecks_that_money_is_still_held(): void
    {
        $this->activity->payment->forceFill([
            'status' => PaymentStatus::Refunded,
            'refunded_at' => now(),
        ])->save();

        try {
            app(StartActivityAction::class)->handle($this->activity->refresh());
            $this->fail('dana yang sudah dikembalikan seharusnya menghalangi');
        } catch (PaymentNotHeldException $e) {
            $this->assertSame('payment_not_held', $e->errorCode());
            $this->assertSame(['payment_status' => 'refunded'], $e->context());
        }
    }

    public function test_starting_twice_is_rejected(): void
    {
        app(StartActivityAction::class)->handle($this->activity);

        $this->expectException(InvalidStatusTransitionException::class);

        app(StartActivityAction::class)->handle($this->activity->refresh());
    }

    public function test_submit_records_the_proof(): void
    {
        app(StartActivityAction::class)->handle($this->activity);

        $submitted = app(SubmitActivityAction::class)->handle(
            new SubmitActivityData('Sudah beres', ['p/a.jpg', 'p/b.jpg']),
            $this->activity->refresh(),
        );

        $this->assertSame(ActivityStatus::Submitted, $submitted->status);
        $this->assertNotNull($submitted->submitted_at);
        $this->assertSame('Sudah beres', $submitted->worker_note);
        $this->assertSame(['p/a.jpg', 'p/b.jpg'], $submitted->proof_photos);
        $this->assertSame(TaskStatus::Submitted, $this->task->refresh()->status);
    }

    public function test_submit_stores_null_when_no_photos(): void
    {
        app(StartActivityAction::class)->handle($this->activity);

        $submitted = app(SubmitActivityAction::class)
            ->handle(new SubmitActivityData(null, []), $this->activity->refresh());

        $this->assertNull($submitted->proof_photos);
    }

    public function test_submitting_without_starting_is_rejected(): void
    {
        $this->expectException(InvalidStatusTransitionException::class);

        app(SubmitActivityAction::class)
            ->handle(new SubmitActivityData('x'), $this->activity);
    }

    private function submitted(): Activity
    {
        app(StartActivityAction::class)->handle($this->activity);

        return app(SubmitActivityAction::class)
            ->handle(new SubmitActivityData('Beres', ['p/a.jpg']), $this->activity->refresh());
    }

    public function test_approve_completes_the_task_and_releases_the_money(): void
    {
        $submitted = $this->submitted();

        $approved = app(ApproveActivityAction::class)
            ->handle($submitted, $this->poster, 'Rapi');

        $this->assertSame(ActivityStatus::Approved, $approved->status);
        $this->assertNotNull($approved->approved_at);
        $this->assertSame('Rapi', $approved->poster_note);
        $this->assertSame(TaskStatus::Completed, $this->task->refresh()->status);
        $this->assertNotNull($this->task->refresh()->completed_at);
        $this->assertSame(PaymentStatus::Released, $approved->payment->refresh()->status);
        $this->assertNotNull($approved->payment->refresh()->released_at);
    }

    public function test_approve_increments_worker_tasks_completed(): void
    {
        $before = $this->worker->tasks_completed;

        app(ApproveActivityAction::class)->handle($this->submitted(), $this->poster);

        $this->assertSame($before + 1, $this->worker->refresh()->tasks_completed);
    }

    public function test_approving_an_unsubmitted_activity_is_rejected(): void
    {
        $this->expectException(InvalidStatusTransitionException::class);

        app(ApproveActivityAction::class)->handle($this->activity, $this->poster);
    }

    public function test_reject_disputes_the_task_and_keeps_money_held(): void
    {
        $submitted = $this->submitted();

        $rejected = app(RejectActivityAction::class)
            ->handle($submitted, $this->poster, 'Masih berkerak');

        $this->assertSame(ActivityStatus::Rejected, $rejected->status);
        $this->assertNotNull($rejected->rejected_at);
        $this->assertSame('Masih berkerak', $rejected->poster_note);
        $this->assertSame(TaskStatus::Disputed, $this->task->refresh()->status);
        // Dana TETAP ditahan.
        $this->assertSame(PaymentStatus::Held, $rejected->payment->refresh()->status);
    }

    public function test_worker_can_resubmit_after_rejection(): void
    {
        $submitted = $this->submitted();
        app(RejectActivityAction::class)->handle($submitted, $this->poster, 'ulangi');

        // Task disputed tidak bisa kembali ke submitted, jadi transisi task
        // yang menghalangi — bukan transisi activity.
        $this->expectException(InvalidStatusTransitionException::class);

        app(SubmitActivityAction::class)
            ->handle(new SubmitActivityData('sudah diperbaiki'), $submitted->refresh());
    }

    public function test_list_activities_for_worker(): void
    {
        $page = app(ListActivitiesAction::class)->forWorker($this->worker, new CursorPageData(20));

        $this->assertCount(1, $page->items());
        $this->assertNotNull($page->first()->task);
        $this->assertNotNull($page->first()->payment);
    }

    public function test_list_activities_excludes_other_workers(): void
    {
        $other = $this->activeUser();

        $page = app(ListActivitiesAction::class)->forWorker($other, new CursorPageData(20));

        $this->assertCount(0, $page->items());
    }
}
