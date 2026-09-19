<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Task;

use App\Actions\Bid\AcceptBidAction;
use App\Actions\Task\StartWithCurrentWorkersAction;
use App\Enums\ActivityStatus;
use App\Enums\ActorType;
use App\Enums\BidStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Bid;
use App\Models\Task;
use App\Models\TaskStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * DEAL MEMBUKA PEKERJAAN — aturan tetap, bukan keadaan sementara.
 *
 * Begitu lelang ditutup, setiap orang yang diterima punya satu activity atas
 * namanya dan task berpindah ke `active`. Sebelumnya baris itu baru lahir saat
 * pengelola mengonfirmasi transfer, sehingga pekerja yang SUDAH dipilih tidak
 * menemukan kerjaannya di mana pun: ada di daftar pemberi kerja, tidak ada di
 * daftarnya sendiri, tanpa satu pun keterangan kenapa.
 *
 * Uang tidak berhenti menjaga apa pun — ia pindah menjaga hal yang tepat.
 * Yang dikunci `held` adalah MULAI BEKERJA dan pelepasan upah, bukan
 * keberadaan barisnya. Karena itu kelas ini berjalan dengan gerbang
 * pembayaran HIDUP (bawaan suite): aturannya tidak bergantung pada saklar itu.
 */
final class DealOpensWorkTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    private User $worker;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->assertTrue(
            (bool) config('sekarya.payments.gate_enabled'),
            'kelas ini menguji bahwa aturannya berlaku JUGA saat gerbang pembayaran hidup',
        );

        $this->poster = $this->activeUser();
        $this->worker = $this->activeUser();
        $this->task = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Open,
            'budget_min' => 150_000,
            'budget_max' => null,
        ]);
    }

    private function pendingBid(?User $bidder = null, int $amount = 220_000): Bid
    {
        return Bid::factory()->create([
            'task_id' => $this->task->getKey(),
            'bidder_id' => ($bidder ?? $this->worker)->getKey(),
            'amount' => $amount,
            'status' => BidStatus::Pending,
        ]);
    }

    private function accept(?Bid $bid = null): Task
    {
        return app(AcceptBidAction::class)->handle($bid ?? $this->pendingBid(), $this->poster);
    }

    public function test_accepting_the_last_bid_opens_the_activity(): void
    {
        $this->accept();

        $activity = Activity::query()->where('task_id', $this->task->getKey())->sole();

        $this->assertSame(ActivityStatus::Open, $activity->status);
        $this->assertSame($this->worker->getKey(), $activity->worker_id);
        // Harga PER ORANG, dari penawarannya sendiri — bukan total task.
        $this->assertSame(220_000, (int) $activity->agreed_amount);
        $this->assertNotNull($activity->opened_at);
        $this->assertSame(TaskStatus::Active, $this->task->refresh()->status);
    }

    /** Kesepakatannya tetap tercatat; task hanya tidak berhenti di sana. */
    public function test_the_deal_is_still_recorded(): void
    {
        $this->accept();

        $task = $this->task->refresh();
        $this->assertNotNull($task->dealt_at);

        $moves = TaskStatusLog::query()
            ->where('task_id', $task->getKey())
            ->orderBy('id')
            ->pluck('to_status')
            ->all();

        $this->assertSame([TaskStatus::Dealt->value, TaskStatus::Active->value], $moves);
        $this->assertSame(
            ActorType::Poster,
            TaskStatusLog::query()
                ->where('task_id', $task->getKey())
                ->where('to_status', TaskStatus::Active->value)
                ->value('actor_type'),
        );
    }

    /** Membuka pekerjaan bukan menyatakan uangnya sudah masuk. */
    public function test_it_does_not_touch_the_payment(): void
    {
        $this->accept();

        $payment = $this->task->refresh()->payment;

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->held_at);
    }

    /** Slot yang belum penuh berarti lelang belum ditutup — belum ada yang dibuka. */
    public function test_nothing_opens_while_slots_remain(): void
    {
        $this->task->forceFill(['workers_needed' => 2])->save();

        $this->accept($this->pendingBid($this->worker, 200_000));

        $this->assertSame(TaskStatus::Open, $this->task->refresh()->status);
        $this->assertSame(0, Activity::query()->where('task_id', $this->task->getKey())->count());
    }

    public function test_every_hired_worker_gets_one_activity(): void
    {
        $this->task->forceFill(['workers_needed' => 2])->save();
        $second = $this->activeUser();

        $this->accept($this->pendingBid($this->worker, 200_000));
        $this->accept($this->pendingBid($second, 180_000));

        $this->assertEqualsCanonicalizing(
            [200_000, 180_000],
            Activity::query()
                ->where('task_id', $this->task->getKey())
                ->get()
                ->map(fn (Activity $a): int => (int) $a->agreed_amount)
                ->all(),
        );
    }

    /** Mulai lebih awal menutup lelang lewat jalur lain — hasilnya sama. */
    public function test_starting_early_opens_the_work_too(): void
    {
        $this->task->forceFill(['workers_needed' => 3])->save();
        $this->accept($this->pendingBid());

        app(StartWithCurrentWorkersAction::class)->handle($this->task->refresh(), $this->poster);

        $this->assertSame(TaskStatus::Active, $this->task->refresh()->status);
        $this->assertSame(1, Activity::query()->where('task_id', $this->task->getKey())->count());
    }

    /**
     * Konfirmasi transfer yang datang menyusul tidak menggandakan apa pun.
     *
     * Jalur pengelola masih memanggil pembukaan yang sama — untuk task warisan
     * yang barisnya memang belum ada. Di task yang sudah terbuka, yang terjadi
     * hanya dananya ditahan.
     */
    public function test_confirming_the_transfer_afterwards_is_idempotent(): void
    {
        $this->accept();

        $this->openActivities($this->task->refresh(), $this->poster);

        $this->assertSame(1, Activity::query()->where('task_id', $this->task->getKey())->count());
        $this->assertSame(TaskStatus::Active, $this->task->refresh()->status);
        $this->assertSame(PaymentStatus::Held, $this->task->refresh()->payment->status);
        $this->assertSame(
            1,
            TaskStatusLog::query()
                ->where('task_id', $this->task->getKey())
                ->where('to_status', TaskStatus::Active->value)
                ->count(),
        );
    }
}
