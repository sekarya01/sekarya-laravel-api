<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Task;

use App\Actions\Task\CancelTaskAction;
use App\Actions\Task\CreateTaskAction;
use App\Actions\Task\PublishTaskAction;
use App\Data\Task\CreateTaskData;
use App\Enums\ActorType;
use App\Enums\BidStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Bid;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TaskStatusLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TaskActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
    }

    private function data(array $override = []): CreateTaskData
    {
        return new CreateTaskData(
            categoryId: $override['categoryId'] ?? $this->anyCategory()->getKey(),
            title: $override['title'] ?? 'Cuci AC 2 unit',
            description: $override['description'] ?? 'Servis AC split dan cuci evaporator.',
            budgetMin: $override['budgetMin'] ?? 150_000,
            budgetMax: $override['budgetMax'] ?? null,
            options: $override['options'] ?? [],
            skillSlugs: $override['skillSlugs'] ?? [],
            publishNow: $override['publishNow'] ?? false,
        );
    }

    public function test_it_creates_a_draft_by_default(): void
    {
        $poster = $this->activeUser();

        $task = app(CreateTaskAction::class)->handle($this->data(), $poster);

        $this->assertSame(TaskStatus::Draft, $task->status);
    }

    public function test_publish_now_opens_the_task_immediately(): void
    {
        $poster = $this->activeUser();

        $task = app(CreateTaskAction::class)->handle($this->data(['publishNow' => true]), $poster);

        $this->assertSame(TaskStatus::Open, $task->status);
    }

    /** budget_max opsional: null berarti tanpa batas atas, bukan nol. */
    public function test_budget_max_stays_null_when_not_given(): void
    {
        $poster = $this->activeUser();

        $task = app(CreateTaskAction::class)->handle($this->data(), $poster);

        $this->assertNull($task->budget_max);
        $this->assertSame(150_000, $task->budget_min);
    }

    public function test_reference_price_is_snapshotted_from_the_category(): void
    {
        $poster = $this->activeUser();
        $category = $this->anyCategory();
        $category->forceFill(['ref_price_median' => 123_456])->save();

        $task = app(CreateTaskAction::class)
            ->handle($this->data(['categoryId' => $category->getKey()]), $poster);

        $this->assertSame(123_456, $task->ref_price_median);

        // Angka kategori berubah, salinan di task tidak.
        $category->forceFill(['ref_price_median' => 999_999])->save();
        $this->assertSame(123_456, $task->refresh()->ref_price_median);
    }

    public function test_it_attaches_skills(): void
    {
        $poster = $this->activeUser();

        $task = app(CreateTaskAction::class)
            ->handle($this->data(['skillSlugs' => ['cuci-ac', 'setrika']]), $poster);

        $this->assertEqualsCanonicalizing(
            ['cuci-ac', 'setrika'],
            $task->skills()->pluck('slug')->all(),
        );
    }

    public function test_it_generates_ulid_and_task_number(): void
    {
        $task = app(CreateTaskAction::class)->handle($this->data(), $this->activeUser());

        $this->assertSame(26, strlen((string) $task->ulid));
        $this->assertMatchesRegularExpression('/^TK-\d{6}-[A-Z0-9]{6}$/', $task->task_number);
    }

    public function test_it_writes_the_first_status_log_with_null_from(): void
    {
        $poster = $this->activeUser();

        $task = app(CreateTaskAction::class)->handle($this->data(['publishNow' => true]), $poster);

        $log = TaskStatusLog::query()->where('task_id', $task->getKey())->firstOrFail();
        $this->assertNull($log->from_status);
        $this->assertSame(TaskStatus::Open->value, $log->to_status);
        $this->assertSame(ActorType::Poster, $log->actor_type);
        $this->assertSame($poster->getKey(), (int) $log->actor_id);
    }

    public function test_it_increments_tasks_posted(): void
    {
        $poster = $this->activeUser();
        $this->assertSame(0, $poster->tasks_posted);

        app(CreateTaskAction::class)->handle($this->data(), $poster);

        $this->assertSame(1, $poster->refresh()->tasks_posted);
    }

    public function test_publish_moves_draft_to_open_and_logs_it(): void
    {
        $poster = $this->activeUser();
        $task = app(CreateTaskAction::class)->handle($this->data(), $poster);

        $published = app(PublishTaskAction::class)->handle($task, $poster);

        $this->assertSame(TaskStatus::Open, $published->status);
        $this->assertSame(2, TaskStatusLog::query()->where('task_id', $task->getKey())->count());
    }

    public function test_publishing_a_completed_task_is_rejected(): void
    {
        $poster = $this->activeUser();
        $task = app(CreateTaskAction::class)->handle($this->data(), $poster);
        $task->forceFill(['status' => TaskStatus::Completed])->save();

        try {
            app(PublishTaskAction::class)->handle($task, $poster);
            $this->fail('perpindahan status seharusnya ditolak');
        } catch (InvalidStatusTransitionException $e) {
            $this->assertSame('invalid_status_transition', $e->errorCode());
            $this->assertSame(['from' => 'completed', 'to' => 'open'], $e->context());
        }
    }

    public function test_cancel_refunds_held_money(): void
    {
        $poster = $this->activeUser();
        $task = app(CreateTaskAction::class)->handle($this->data(['publishNow' => true]), $poster);
        $payment = Payment::factory()->held()->create([
            'task_id' => $task->getKey(),
            'payer_id' => $poster->getKey(),
        ]);

        app(CancelTaskAction::class)->handle($task, $poster, 'rencana berubah');

        $this->assertSame(PaymentStatus::Refunded, $payment->refresh()->status);
        $this->assertNotNull($payment->refunded_at);
    }

    public function test_cancel_cancels_a_pending_payment_instead(): void
    {
        $poster = $this->activeUser();
        $task = app(CreateTaskAction::class)->handle($this->data(['publishNow' => true]), $poster);
        $payment = Payment::factory()->create([
            'task_id' => $task->getKey(),
            'payer_id' => $poster->getKey(),
        ]);

        app(CancelTaskAction::class)->handle($task, $poster);

        $this->assertSame(PaymentStatus::Cancelled, $payment->refresh()->status);
        $this->assertNotNull($payment->cancelled_at);
    }

    public function test_cancel_rejects_pending_bids(): void
    {
        $poster = $this->activeUser();
        $worker = $this->activeUser();
        $task = app(CreateTaskAction::class)->handle($this->data(['publishNow' => true]), $poster);
        $bid = Bid::factory()->create([
            'task_id' => $task->getKey(),
            'bidder_id' => $worker->getKey(),
        ]);

        app(CancelTaskAction::class)->handle($task, $poster);

        $this->assertSame(BidStatus::Rejected, $bid->refresh()->status);
        $this->assertNotNull($bid->responded_at);
    }

    public function test_cancel_records_who_and_why(): void
    {
        $poster = $this->activeUser();
        $task = app(CreateTaskAction::class)->handle($this->data(['publishNow' => true]), $poster);

        $cancelled = app(CancelTaskAction::class)->handle($task, $poster, 'tidak perlu lagi');

        $this->assertSame(TaskStatus::Cancelled, $cancelled->status);
        $this->assertSame('poster', $cancelled->cancelled_by);
        $this->assertSame('tidak perlu lagi', $cancelled->cancellation_reason);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    /** Pembatalan setelah deal adalah sinyal risiko pada orangnya. */
    public function test_cancelling_after_a_deal_counts_against_the_actor(): void
    {
        $poster = $this->activeUser();
        $worker = $this->activeUser();
        $task = app(CreateTaskAction::class)->handle($this->data(['publishNow' => true]), $poster);
        $task->forceFill([
            'status' => TaskStatus::Dealt,
            'dealt_at' => now(),
        ])->save();
        $this->hireWorker($task, $worker);

        app(CancelTaskAction::class)->handle($task, $poster);

        $this->assertSame(1, $poster->refresh()->cancellations);
    }

    public function test_cancelling_before_a_deal_does_not_count(): void
    {
        $poster = $this->activeUser();
        $task = app(CreateTaskAction::class)->handle($this->data(['publishNow' => true]), $poster);

        app(CancelTaskAction::class)->handle($task, $poster);

        $this->assertSame(0, $poster->refresh()->cancellations);
    }

    public function test_worker_cancelling_is_recorded_as_worker(): void
    {
        $poster = $this->activeUser();
        $worker = $this->activeUser();
        $task = app(CreateTaskAction::class)->handle($this->data(['publishNow' => true]), $poster);
        $this->hireWorker($task, $worker);

        $cancelled = app(CancelTaskAction::class)->handle($task, $worker);

        $this->assertSame('worker', $cancelled->cancelled_by);
    }

    public function test_options_are_stored_as_null_when_empty(): void
    {
        $task = app(CreateTaskAction::class)->handle($this->data(), $this->activeUser());

        $this->assertNull($task->options);
    }

    public function test_options_are_stored_when_present(): void
    {
        $task = app(CreateTaskAction::class)->handle(
            $this->data(['options' => [['label' => 'Bawa alat', 'value' => true]]]),
            $this->activeUser(),
        );

        $this->assertSame([['label' => 'Bawa alat', 'value' => true]], $task->options);
    }
}
