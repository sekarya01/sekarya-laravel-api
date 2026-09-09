<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Payment;

use App\Actions\Payment\HoldPaymentAction;
use App\Enums\ActivityStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Activity;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HoldPaymentActionTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    private User $worker;

    private Task $task;

    private Payment $payment;

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
            'dealt_at' => now(),
        ]);
        $this->hireWorker($this->task, $this->worker, 220_000);
        $this->payment = Payment::factory()->create([
            'task_id' => $this->task->getKey(),
            'payer_id' => $this->poster->getKey(),
            'amount' => 220_000,
        ]);
    }

    public function test_it_holds_the_money(): void
    {
        app(HoldPaymentAction::class)->handle($this->task, $this->poster);

        $payment = $this->payment->refresh();
        $this->assertSame(PaymentStatus::Held, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($payment->held_at);
        $this->assertTrue($payment->status->opensActivity());
    }

    public function test_it_opens_the_activity(): void
    {
        $activity = app(HoldPaymentAction::class)->handle($this->task, $this->poster)->sole();

        $this->assertSame(ActivityStatus::Open, $activity->status);
        $this->assertNotNull($activity->opened_at);
        $this->assertSame($this->worker->getKey(), (int) $activity->worker_id);
        $this->assertSame($this->payment->getKey(), (int) $activity->payment_id);
        $this->assertSame(220_000, $activity->agreed_amount);
        $this->assertSame(26, strlen((string) $activity->ulid));
    }

    public function test_it_moves_the_task_to_active(): void
    {
        app(HoldPaymentAction::class)->handle($this->task, $this->poster);

        $this->assertSame(TaskStatus::Active, $this->task->refresh()->status);
    }

    public function test_holding_twice_is_rejected(): void
    {
        app(HoldPaymentAction::class)->handle($this->task, $this->poster);

        try {
            app(HoldPaymentAction::class)->handle($this->task->refresh(), $this->poster);
            $this->fail('transfer kedua seharusnya ditolak');
        } catch (InvalidStatusTransitionException $e) {
            $this->assertSame(['from' => 'held', 'to' => 'held'], $e->context());
        }
    }

    /** Satu pembayaran hanya boleh membuka satu activity. */
    public function test_only_one_activity_exists_per_payment(): void
    {
        app(HoldPaymentAction::class)->handle($this->task, $this->poster);

        try {
            app(HoldPaymentAction::class)->handle($this->task->refresh(), $this->poster);
        } catch (\Throwable) {
            // diharapkan
        }

        $this->assertSame(1, Activity::query()->where('task_id', $this->task->getKey())->count());
    }

    public function test_refunded_payment_cannot_be_held(): void
    {
        $this->payment->forceFill([
            'status' => PaymentStatus::Refunded,
            'refunded_at' => now(),
        ])->save();

        $this->expectException(InvalidStatusTransitionException::class);

        app(HoldPaymentAction::class)->handle($this->task, $this->poster);
    }
}
