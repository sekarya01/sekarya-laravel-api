<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Activity;

use App\Actions\Activity\ApproveActivityAction;
use App\Actions\Activity\RejectActivityAction;
use App\Actions\Activity\StartActivityAction;
use App\Actions\Activity\SubmitActivityAction;
use App\Actions\Bid\AcceptBidAction;
use App\Actions\Bid\PlaceBidAction;
use App\Data\Activity\SubmitActivityData;
use App\Data\Bid\PlaceBidData;
use App\Enums\ActivityStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
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
        $activity = $this->activities[$i];
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

    public function test_approving_one_worker_releases_no_money_and_completes_nothing(): void
    {
        foreach (range(0, 2) as $i) {
            $this->submit($i);
        }

        app(ApproveActivityAction::class)->handle($this->activities[0]->refresh(), $this->poster);

        $this->assertSame(ActivityStatus::Approved, $this->activities[0]->refresh()->status);
        $this->assertSame(TaskStatus::Submitted, $this->task->refresh()->status);
        $this->assertNull($this->task->completed_at);

        $payment = Payment::query()->where('task_id', $this->task->getKey())->sole();
        $this->assertSame(PaymentStatus::Held, $payment->status);
        $this->assertNull($payment->released_at);

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

    // ── Penolakan ───────────────────────────────────────────────────────────

    /**
     * Menolak satu orang saat yang lain masih bekerja tidak boleh menggagalkan
     * permintaannya hanya karena task belum bisa berpindah ke `disputed`.
     */
    public function test_rejecting_one_worker_while_others_still_work_is_recorded_on_the_activity(): void
    {
        $this->submit(0);

        app(RejectActivityAction::class)->handle($this->activities[0]->refresh(), $this->poster, 'ulangi');

        $this->assertSame(ActivityStatus::Rejected, $this->activities[0]->refresh()->status);
        $this->assertSame(TaskStatus::Active, $this->task->refresh()->status);
    }

    public function test_rejecting_after_everyone_submitted_disputes_the_task(): void
    {
        foreach (range(0, 2) as $i) {
            $this->submit($i);
        }

        app(RejectActivityAction::class)->handle($this->activities[1]->refresh(), $this->poster, 'kurang');

        $this->assertSame(TaskStatus::Disputed, $this->task->refresh()->status);
        $this->assertSame(ActivityStatus::Rejected, $this->activities[1]->refresh()->status);

        // Yang lain tidak ikut ditarik mundur.
        $this->assertSame(ActivityStatus::Submitted, $this->activities[0]->refresh()->status);
    }

    /** Sengketa menahan pelepasan dana sampai diselesaikan. */
    public function test_money_stays_held_while_one_worker_is_disputed(): void
    {
        foreach (range(0, 2) as $i) {
            $this->submit($i);
        }
        app(RejectActivityAction::class)->handle($this->activities[1]->refresh(), $this->poster, 'kurang');

        app(ApproveActivityAction::class)->handle($this->activities[0]->refresh(), $this->poster);
        app(ApproveActivityAction::class)->handle($this->activities[2]->refresh(), $this->poster);

        $payment = Payment::query()->where('task_id', $this->task->getKey())->sole();

        $this->assertSame(PaymentStatus::Held, $payment->status);
        $this->assertSame(TaskStatus::Disputed, $this->task->refresh()->status);
    }
}
