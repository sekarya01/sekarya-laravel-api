<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Activity;

use App\Actions\Activity\ApproveActivityAction;
use App\Actions\Activity\StartActivityAction;
use App\Actions\Activity\SubmitActivityAction;
use App\Actions\Admin\Dispute\ResolveDisputeAction;
use App\Actions\Bid\AcceptBidAction;
use App\Actions\Bid\PlaceBidAction;
use App\Actions\Task\CancelTaskAction;
use App\Data\Activity\SubmitActivityData;
use App\Data\Bid\PlaceBidData;
use App\Enums\ActivityStatus;
use App\Enums\DisputeResolution;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Enums\WalletEntryType;
use App\Exceptions\Domain\TaskNotCancellableException;
use App\Models\Activity;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TaskDispute;
use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Kemajuan pekerjaan ketika satu task punya banyak pekerja.
 *
 * Status TASK harus mengikuti AGREGAT seluruh pekerja, bukan siapa yang
 * kebetulan paling cepat. Dua kegagalan yang dicegah kelas ini, dan keduanya
 * bukan hipotetis — keduanya adalah perilaku kode ini sebelum diperbaiki:
 *
 *   1. Penyerahan pertama menandai seluruh task `submitted`, sehingga
 *      penyerahan pekerja berikutnya ditolak karena statusnya sudah pindah.
 *   2. Persetujuan pertama melepas SELURUH dana dan menyelesaikan task,
 *      sehingga pekerja lain — yang upahnya ada di dalam tagihan yang sama —
 *      tidak akan pernah bisa disetujui, apalagi dibayar.
 */
final class MultiWorkerProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    /** @var list<User> */
    private array $workers = [];

    private Task $task;

    /** @var Collection<int, Activity> */
    private Collection $activities;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->poster = $this->activeUser();
        $this->task = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Open,
            'budget_min' => 100_000,
            'workers_needed' => 3,
        ]);

        foreach ([100_000, 200_000, 300_000] as $amount) {
            $worker = $this->activeUser();
            $this->workers[] = $worker;

            $bid = app(PlaceBidAction::class)->handle(
                new PlaceBidData(amount: $amount, message: 'siap'),
                $this->task,
                $worker,
            );
            app(AcceptBidAction::class)->handle($bid, $this->poster);
        }

        $this->activities = $this->openActivities($this->task->refresh(), $this->poster)
            ->values();
    }

    private function submit(int $i): Activity
    {
        $activity = $this->bringToSite($this->activities[$i]);
        app(StartActivityAction::class)->handle($activity);

        return app(SubmitActivityAction::class)
            ->handle(new SubmitActivityData('beres', ['p/a.jpg']), $activity->refresh());
    }

    // ── Penyerahan ──────────────────────────────────────────────────────────

    public function test_one_worker_submitting_does_not_move_the_whole_task(): void
    {
        $this->submit(0);

        $this->assertSame(ActivityStatus::Submitted, $this->activities[0]->refresh()->status);

        // Yang lain belum bergerak, jadi task masih berjalan.
        $this->assertSame(TaskStatus::Active, $this->task->refresh()->status);
        $this->assertSame(ActivityStatus::Open, $this->activities[1]->refresh()->status);
    }

    public function test_the_task_is_submitted_only_when_everyone_has_submitted(): void
    {
        $this->submit(0);
        $this->submit(1);
        $this->assertSame(TaskStatus::Active, $this->task->refresh()->status);

        $this->submit(2);

        $this->assertSame(TaskStatus::Submitted, $this->task->refresh()->status);
    }

    // ── Persetujuan & uang ──────────────────────────────────────────────────

    public function test_approving_one_worker_pays_only_that_worker_and_completes_nothing(): void
    {
        foreach (range(0, 2) as $i) {
            $this->submit($i);
        }

        app(ApproveActivityAction::class)->handle($this->activities[0]->refresh(), $this->poster);

        $this->assertSame(ActivityStatus::Approved, $this->activities[0]->refresh()->status);
        $this->assertSame(TaskStatus::Submitted, $this->task->refresh()->status);
        $this->assertNull($this->task->completed_at);

        // Tagihan tetap ditahan sampai semua mitra diputuskan…
        $payment = Payment::query()->where('task_id', $this->task->getKey())->sole();
        $this->assertSame(PaymentStatus::Held, $payment->status);
        $this->assertNull($payment->released_at);

        // …tapi upah orang ini sudah dibayar, tidak menunggu yang lain.
        $this->assertTrue($this->earned($this->activities[0]));
        $this->assertFalse($this->earned($this->activities[1]));

        // Orang ini memang sudah menyelesaikan bagiannya.
        $this->assertSame(1, $this->reputationOf($this->workers[0])->tasks_completed);
        $this->assertSame(0, $this->reputationOf($this->workers[1])->tasks_completed);
    }

    public function test_the_money_is_released_once_when_the_last_worker_is_approved(): void
    {
        foreach (range(0, 2) as $i) {
            $this->submit($i);
        }

        foreach (range(0, 2) as $i) {
            app(ApproveActivityAction::class)->handle($this->activities[$i]->refresh(), $this->poster);
        }

        $task = $this->task->refresh();
        $payment = Payment::query()->where('task_id', $task->getKey())->sole();

        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertSame(PaymentStatus::Released, $payment->status);
        $this->assertNotNull($payment->released_at);

        // Satu pelepasan untuk seluruh tagihan, bukan satu per pekerja.
        $this->assertSame(600_000, $payment->amount);

        foreach ($this->workers as $worker) {
            $this->assertSame(1, $this->reputationOf($worker)->tasks_completed);
        }
    }

    /** Jejak status task tidak boleh mencatat "selesai" tiga kali. */
    public function test_the_task_is_completed_exactly_once(): void
    {
        foreach (range(0, 2) as $i) {
            $this->submit($i);
        }
        foreach (range(0, 2) as $i) {
            app(ApproveActivityAction::class)->handle($this->activities[$i]->refresh(), $this->poster);
        }

        $this->assertSame(1, $this->task->statusLogs()
            ->where('to_status', TaskStatus::Completed->value)->count());
    }

    // ── Sengketa per mitra ──────────────────────────────────────────────────

    public function test_disputing_one_worker_while_others_still_work_keeps_the_task_active(): void
    {
        $this->submit(0);

        $this->raiseDispute($this->activities[0]->refresh(), $this->poster);

        $this->assertSame(ActivityStatus::Rejected, $this->activities[0]->refresh()->status);
        $this->assertSame(TaskStatus::Active, $this->task->refresh()->status);

        // Sisanya menyerahkan → tidak ada yang bekerja, satu disengketakan.
        $this->submit(1);
        $this->submit(2);
        $this->assertSame(TaskStatus::Disputed, $this->task->refresh()->status);
    }

    public function test_disputing_after_everyone_submitted_disputes_the_task_only(): void
    {
        foreach (range(0, 2) as $i) {
            $this->submit($i);
        }

        $this->raiseDispute($this->activities[1]->refresh(), $this->poster);

        $this->assertSame(TaskStatus::Disputed, $this->task->refresh()->status);
        $this->assertSame(ActivityStatus::Rejected, $this->activities[1]->refresh()->status);

        // Yang lain tidak ikut ditarik mundur.
        $this->assertSame(ActivityStatus::Submitted, $this->activities[0]->refresh()->status);
    }

    /** Mitra yang kerjanya baik dibayar walau ada sengketa mitra lain. */
    public function test_other_workers_are_paid_while_one_worker_is_disputed(): void
    {
        $this->disputeTheMiddleWorkerAndApproveTheRest();

        $payment = Payment::query()->where('task_id', $this->task->getKey())->sole();

        $this->assertSame(PaymentStatus::Held, $payment->status);
        $this->assertSame(TaskStatus::Disputed, $this->task->refresh()->status);
        $this->assertTrue($this->earned($this->activities[0]));
        $this->assertTrue($this->earned($this->activities[2]));
        $this->assertFalse($this->earned($this->activities[1]));
    }

    public function test_releasing_the_dispute_pays_that_worker_and_completes_the_task(): void
    {
        $dispute = $this->disputeTheMiddleWorkerAndApproveTheRest();

        app(ResolveDisputeAction::class)->handle($dispute, $this->activeAdmin(), DisputeResolution::Release, 'Foto hasil sesuai kesepakatan.');

        $task = $this->task->refresh();
        $payment = Payment::query()->where('task_id', $task->getKey())->sole();
        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertSame(PaymentStatus::Released, $payment->status);
        $this->assertSame(ActivityStatus::Approved, $this->activities[1]->refresh()->status);
        $this->assertTrue($this->earned($this->activities[1]));
        $this->assertSame(0, (int) $this->poster->walletOrNew()->balance);
        $this->assertSame(1, $this->reputationOf($this->workers[1])->tasks_completed);
    }

    /** Pengembalian hanya upah mitra yang disengketakan — bukan seluruh tagihan. */
    public function test_refunding_the_dispute_returns_only_that_workers_wage(): void
    {
        $dispute = $this->disputeTheMiddleWorkerAndApproveTheRest();

        app(ResolveDisputeAction::class)->handle($dispute, $this->activeAdmin(), DisputeResolution::Refund, 'Hasil tidak sesuai foto awal.');

        $task = $this->task->refresh();
        $payment = Payment::query()->where('task_id', $task->getKey())->sole();
        $this->assertSame(ActivityStatus::Refunded, $this->activities[1]->refresh()->status);
        // Dua mitra tetap dibayar → task selesai, tagihan dilepas.
        $this->assertSame(TaskStatus::Completed, $task->status);
        $this->assertSame(PaymentStatus::Released, $payment->status);
        $this->assertSame(200_000, $this->poster->refresh()->walletOrNew()->balance);
        $this->assertFalse($this->earned($this->activities[1]));
        $this->assertSame(0, $this->reputationOf($this->workers[1])->tasks_completed);
    }

    public function test_a_task_with_an_open_dispute_cannot_be_cancelled(): void
    {
        $this->submit(0);
        $this->raiseDispute($this->activities[0]->refresh(), $this->poster);

        try {
            app(CancelTaskAction::class)->handle($this->task->refresh(), $this->poster);
            $this->fail('task bersengketa terbuka tidak boleh dibatalkan');
        } catch (TaskNotCancellableException $e) {
            $this->assertSame('open_dispute', $e->context()['reason']);
        }

        $this->assertSame(TaskStatus::Active, $this->task->refresh()->status);
    }

    /** Upah yang sudah dibayar tidak ikut dikembalikan saat dibatalkan. */
    public function test_cancelling_after_a_payout_refunds_only_the_remainder(): void
    {
        $this->submit(0);
        app(ApproveActivityAction::class)->handle($this->activities[0]->refresh(), $this->poster);

        app(CancelTaskAction::class)->handle($this->task->refresh(), $this->poster);

        $payment = Payment::query()->where('task_id', $this->task->getKey())->sole();
        $this->assertSame(500_000, $this->poster->refresh()->walletOrNew()->balance);
        $this->assertSame(PaymentStatus::Released, $payment->status);
    }

    private function disputeTheMiddleWorkerAndApproveTheRest(): TaskDispute
    {
        foreach (range(0, 2) as $i) {
            $this->submit($i);
        }
        $dispute = $this->raiseDispute($this->activities[1]->refresh(), $this->poster);

        app(ApproveActivityAction::class)->handle($this->activities[0]->refresh(), $this->poster);
        app(ApproveActivityAction::class)->handle($this->activities[2]->refresh(), $this->poster);

        return $dispute;
    }

    private function earned(Activity $activity): bool
    {
        return WalletEntry::query()
            ->where('reference_type', 'activities')
            ->where('reference_id', $activity->getKey())
            ->where('type', WalletEntryType::Earning)
            ->exists();
    }
}
