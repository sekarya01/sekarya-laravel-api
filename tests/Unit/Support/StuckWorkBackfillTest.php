<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\ActivityStatus;
use App\Enums\ActorType;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TaskStatusLog;
use App\Models\User;
use App\Support\StuckWorkBackfill;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menyusulkan pekerjaan yang terlanjur tersangkut di `dealt`.
 *
 * Task yang deal sebelum gerbang pembayaran dimatikan tidak punya activity dan
 * tidak akan pernah mendapatkannya sendiri. Kelas ini yang menyusulkannya.
 *
 * Suite berjalan dengan gerbangnya HIDUP (phpunit.xml). Penyusulan ini tidak
 * bergantung pada saklar itu — ada satu test yang membuktikannya — tapi test
 * lain di sini mematikannya supaya berangkat dari keadaan produksi hari ini.
 */
final class StuckWorkBackfillTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        config(['sekarya.payments.gate_enabled' => false]);

        $this->poster = $this->activeUser();
        $this->worker = $this->activeUser();
    }

    /** Task yang sudah deal, punya tagihan, tapi belum punya activity. */
    private function stuckTask(int $amount = 220_000): Task
    {
        $task = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Dealt,
            'dealt_at' => now(),
        ]);
        $this->hireWorker($task, $this->worker, $amount);
        Payment::factory()->create([
            'task_id' => $task->getKey(),
            'payer_id' => $this->poster->getKey(),
            'amount' => $amount,
        ]);

        return $task->refresh();
    }

    private function backfill(): StuckWorkBackfill
    {
        return app(StuckWorkBackfill::class);
    }

    public function test_it_opens_the_work_that_was_stuck(): void
    {
        $task = $this->stuckTask();

        $opened = $this->backfill()->run();

        $this->assertSame([(int) $task->getKey()], $opened);

        $activity = Activity::query()->where('task_id', $task->getKey())->sole();
        $this->assertSame(ActivityStatus::Open, $activity->status);
        $this->assertSame($this->worker->getKey(), $activity->worker_id);
        $this->assertSame(220_000, (int) $activity->agreed_amount);
        $this->assertSame(TaskStatus::Active, $task->refresh()->status);
    }

    /** Menyusulkan pekerjaan tidak menyentuh uang. */
    public function test_it_does_not_touch_the_payment(): void
    {
        $task = $this->stuckTask();

        $this->backfill()->run();

        $payment = $task->refresh()->payment;
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->held_at);
    }

    /** Pelakunya sistem: tidak ada orang yang menekan apa pun hari itu. */
    public function test_the_status_log_names_the_system_as_actor(): void
    {
        $task = $this->stuckTask();

        $this->backfill()->run();

        $log = TaskStatusLog::query()
            ->where('task_id', $task->getKey())
            ->where('to_status', TaskStatus::Active->value)
            ->sole();

        $this->assertSame(ActorType::System, $log->actor_type);
        $this->assertNull($log->actor_id);
        $this->assertStringContainsString('tersangkut', (string) $log->reason);
    }

    public function test_running_twice_changes_nothing(): void
    {
        $task = $this->stuckTask();

        $this->backfill()->run();
        $second = $this->backfill()->run();

        $this->assertSame([], $second);
        $this->assertSame(1, Activity::query()->where('task_id', $task->getKey())->count());
        $this->assertSame(
            1,
            TaskStatusLog::query()
                ->where('task_id', $task->getKey())
                ->where('to_status', TaskStatus::Active->value)
                ->count(),
        );
    }

    /** Lelang yang masih terbuka bukan urusan penyusulan ini. */
    public function test_it_leaves_tasks_that_are_still_hiring_alone(): void
    {
        $open = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Open,
            'workers_needed' => 3,
        ]);
        $this->hireWorker($open, $this->worker, 200_000);
        Payment::factory()->create([
            'task_id' => $open->getKey(),
            'payer_id' => $this->poster->getKey(),
            'amount' => 200_000,
        ]);

        $this->assertSame([], $this->backfill()->run());
        $this->assertSame(0, Activity::query()->where('task_id', $open->getKey())->count());
        $this->assertSame(TaskStatus::Open, $open->refresh()->status);
    }

    /**
     * Berlaku juga saat gerbang pembayaran hidup.
     *
     * Aturannya bukan "gerbang mati membuka pekerjaan", melainkan "deal punya
     * activity". Task warisan yang tersangkut karena itu tetap disusulkan;
     * yang menahannya mulai bekerja tetap dananya, di StartActivityAction.
     */
    public function test_it_runs_with_the_payment_gate_on_too(): void
    {
        config(['sekarya.payments.gate_enabled' => true]);
        $task = $this->stuckTask();

        $this->assertSame([(int) $task->getKey()], $this->backfill()->run());
        $this->assertSame(1, Activity::query()->where('task_id', $task->getKey())->count());
        $this->assertSame(TaskStatus::Active, $task->refresh()->status);
        $this->assertSame(PaymentStatus::Pending, $task->refresh()->payment->status);
    }

    /** Banyak pekerja, satu tagihan: masing-masing dapat activity sendiri. */
    public function test_every_hired_worker_gets_one_activity(): void
    {
        $second = $this->activeUser();
        $task = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Dealt,
            'dealt_at' => now(),
            'workers_needed' => 2,
        ]);
        $this->hireWorker($task, $this->worker, 200_000);
        $this->hireWorker($task, $second, 180_000);
        Payment::factory()->create([
            'task_id' => $task->getKey(),
            'payer_id' => $this->poster->getKey(),
            'amount' => 380_000,
        ]);

        $this->backfill()->run();

        $this->assertEqualsCanonicalizing(
            [200_000, 180_000],
            Activity::query()
                ->where('task_id', $task->getKey())
                ->get()
                ->map(fn (Activity $a): int => (int) $a->agreed_amount)
                ->all(),
        );
    }
}
