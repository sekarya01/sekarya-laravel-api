<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Activity;

use App\Actions\Activity\ApproveActivityAction;
use App\Actions\Activity\RejectActivityAction;
use App\Actions\Activity\StartActivityAction;
use App\Actions\Activity\SubmitActivityAction;
use App\Data\Activity\SubmitActivityData;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Activity;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Cabang penjagaan yang tidak dilewati alur normal. */
final class ActivityGuardsTest extends TestCase
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

    /** Menolak hasil yang belum diserahkan tidak boleh bisa. */
    public function test_rejecting_an_unsubmitted_activity_is_refused(): void
    {
        try {
            app(RejectActivityAction::class)->handle($this->activity, $this->poster, 'belum apa-apa');
            $this->fail('activity yang belum diserahkan seharusnya tidak bisa ditolak');
        } catch (InvalidStatusTransitionException $e) {
            $this->assertSame(['from' => 'open', 'to' => 'rejected'], $e->context());
        }
    }

    public function test_rejecting_an_approved_activity_is_refused(): void
    {
        $submitted = $this->submitted();
        app(ApproveActivityAction::class)->handle($submitted, $this->poster);

        $this->expectException(InvalidStatusTransitionException::class);

        app(RejectActivityAction::class)->handle($submitted->refresh(), $this->poster);
    }

    /**
     * Activity sudah diserahkan tapi dananya ternyata sudah dilepas atau
     * dikembalikan — persetujuan harus gagal, bukan melepas dua kali.
     */
    public function test_approving_refuses_when_the_money_can_no_longer_be_released(): void
    {
        $submitted = $this->submitted();
        $submitted->payment->forceFill([
            'status' => PaymentStatus::Refunded,
            'refunded_at' => now(),
        ])->save();

        try {
            app(ApproveActivityAction::class)->handle($submitted->refresh(), $this->poster);
            $this->fail('dana yang sudah dikembalikan tidak boleh dilepas');
        } catch (InvalidStatusTransitionException $e) {
            $this->assertSame(['from' => 'refunded', 'to' => 'released'], $e->context());
        }
    }

    public function test_the_task_stays_submitted_when_approval_fails(): void
    {
        $submitted = $this->submitted();
        $submitted->payment->forceFill([
            'status' => PaymentStatus::Refunded,
            'refunded_at' => now(),
        ])->save();

        try {
            app(ApproveActivityAction::class)->handle($submitted->refresh(), $this->poster);
        } catch (InvalidStatusTransitionException) {
            // diharapkan
        }

        // Transaksi di-rollback: tidak ada perubahan setengah jadi.
        $this->assertSame(TaskStatus::Submitted, $this->task->refresh()->status);
        $this->assertNull($this->task->refresh()->completed_at);
    }

    private function submitted(): Activity
    {
        app(StartActivityAction::class)->handle($this->activity);

        return app(SubmitActivityAction::class)
            ->handle(new SubmitActivityData('Beres', ['p/a.jpg']), $this->activity->refresh());
    }
}
