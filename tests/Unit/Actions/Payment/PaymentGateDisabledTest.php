<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Payment;

use App\Actions\Activity\ApproveActivityAction;
use App\Actions\Activity\StartActivityAction;
use App\Actions\Activity\SubmitActivityAction;
use App\Actions\Bid\AcceptBidAction;
use App\Actions\Task\StartWithCurrentWorkersAction;
use App\Data\Activity\SubmitActivityData;
use App\Enums\ActivityStatus;
use App\Enums\BidStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Enums\WalletEntryType;
use App\Models\Activity;
use App\Models\Bid;
use App\Models\Task;
use App\Models\TaskStatusLog;
use App\Models\User;
use App\Models\WalletEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alur pekerjaan selagi gerbang pembayaran DIMATIKAN.
 *
 * Keadaan sementara: mekanisme pembayaran belum dikembangkan, jadi
 * `config('sekarya.payments.gate_enabled')` bawaannya mati dan pekerjaan
 * dibuka bersama penutupan lelang. Tanpa itu task berhenti di `dealt`
 * selamanya — satu-satunya jalan ke `active` adalah konfirmasi pengelola atas
 * transfer yang belum ada mekanismenya.
 *
 * Yang dijaga kelas ini bukan sekadar "pekerja bisa mulai", melainkan dua hal
 * yang mudah hilang saat pemeriksaan dilewati:
 *
 *  1. Pembayaran TIDAK dipalsukan. Ia tetap `pending` dari awal sampai task
 *     selesai; tidak ada baris yang menyatakan uang sudah masuk.
 *  2. `pending → held` tetap mustahil. Gerbang yang mati melewati
 *     PEMERIKSAAN, bukan melonggarkan PaymentStatus::canTransitionTo().
 *
 * Suite ini berjalan dengan gerbang hidup (phpunit.xml), jadi setiap test di
 * sini mematikannya sendiri.
 */
final class PaymentGateDisabledTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    private User $worker;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        config(['sekarya.payments.gate_enabled' => false]);

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

    public function test_closing_the_auction_opens_the_work(): void
    {
        $this->accept();

        $activity = Activity::query()->where('task_id', $this->task->getKey())->sole();

        $this->assertSame(ActivityStatus::Open, $activity->status);
        $this->assertSame($this->worker->getKey(), $activity->worker_id);
        // Harga per orang, bukan total task.
        $this->assertSame(220_000, (int) $activity->agreed_amount);
        $this->assertNotNull($activity->opened_at);
        $this->assertSame(TaskStatus::Active, $this->task->refresh()->status);
    }

    /** Yang dilewati pemeriksaannya, bukan catatannya. */
    public function test_payment_is_not_faked_as_held(): void
    {
        $this->accept();

        $payment = $this->task->refresh()->payment;

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertNull($payment->held_at);
    }

    /** Jejaknya tetap lengkap: dealt DAN active, dengan alasan yang jujur. */
    public function test_both_status_moves_are_recorded(): void
    {
        $this->accept();

        $moves = TaskStatusLog::query()
            ->where('task_id', $this->task->getKey())
            ->orderBy('id')
            ->pluck('to_status')
            ->all();

        $this->assertSame([TaskStatus::Dealt->value, TaskStatus::Active->value], $moves);
        $this->assertStringContainsString(
            'gerbang pembayaran dimatikan',
            (string) TaskStatusLog::query()
                ->where('task_id', $this->task->getKey())
                ->where('to_status', TaskStatus::Active->value)
                ->value('reason'),
        );
    }

    public function test_worker_can_start_without_money_being_held(): void
    {
        $this->accept();
        $activity = Activity::query()->where('task_id', $this->task->getKey())->sole();

        $started = app(StartActivityAction::class)->handle($activity);

        $this->assertSame(ActivityStatus::InProgress, $started->status);
        $this->assertSame(PaymentStatus::Pending, $started->payment->refresh()->status);
    }

    /** Ujung ke ujung: terima → mulai → serahkan → setujui. */
    public function test_the_whole_flow_closes_without_a_payment(): void
    {
        $this->accept();
        $activity = Activity::query()->where('task_id', $this->task->getKey())->sole();

        app(StartActivityAction::class)->handle($activity);
        app(SubmitActivityAction::class)->handle(
            new SubmitActivityData('Sudah beres', []),
            $activity->refresh(),
        );
        $this->assertSame(TaskStatus::Submitted, $this->task->refresh()->status);

        app(ApproveActivityAction::class)->handle($activity->refresh(), $this->poster);

        $this->assertSame(TaskStatus::Completed, $this->task->refresh()->status);
        $this->assertNotNull($this->task->refresh()->completed_at);
        // Upahnya tetap masuk — kalau tidak, "konfirmasi selesai" tidak
        // menghasilkan apa pun yang bisa dilihat pekerja.
        $this->assertSame(220_000, (int) WalletEntry::query()
            ->where('type', WalletEntryType::Earning)
            ->where('reference_id', $activity->getKey())
            ->value('amount'));
        // Tapi pembayarannya TIDAK dilepas: tidak ada uang yang pernah masuk.
        $payment = $this->task->refresh()->payment;
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->released_at);
    }

    /** Mulai lebih awal menutup lelang lewat jalur lain — hasilnya sama. */
    public function test_starting_early_opens_the_work_too(): void
    {
        $this->task->forceFill(['workers_needed' => 3])->save();
        $this->accept($this->pendingBid());

        $this->assertSame(TaskStatus::Open, $this->task->refresh()->status);

        app(StartWithCurrentWorkersAction::class)->handle($this->task->refresh(), $this->poster);

        $this->assertSame(TaskStatus::Active, $this->task->refresh()->status);
        $this->assertSame(1, Activity::query()->where('task_id', $this->task->getKey())->count());
    }

    /** Satu activity per pekerja, berapa pun jumlah yang diterima. */
    public function test_every_hired_worker_gets_one_activity(): void
    {
        $this->task->forceFill(['workers_needed' => 2])->save();
        $second = $this->activeUser();

        $this->accept($this->pendingBid($this->worker, 200_000));
        $this->accept($this->pendingBid($second, 180_000));

        $activities = Activity::query()->where('task_id', $this->task->getKey())->get();

        $this->assertCount(2, $activities);
        $this->assertEqualsCanonicalizing(
            [200_000, 180_000],
            $activities->map(fn (Activity $a): int => (int) $a->agreed_amount)->all(),
        );
    }

    /**
     * Gerbang yang dinyalakan lagi tidak menggandakan apa pun.
     *
     * Pekerjaan sudah terbuka, lalu pembayarannya benar-benar dilaporkan dan
     * dikonfirmasi. Yang terjadi hanya dananya ditahan — bukan activity kedua
     * untuk orang yang sama, dan bukan `active → active`.
     */
    public function test_confirming_the_transfer_afterwards_is_idempotent(): void
    {
        $this->accept();

        config(['sekarya.payments.gate_enabled' => true]);
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

    /** Gerbang mati melewati pemeriksaan, bukan melonggarkan aturannya. */
    public function test_pending_to_held_stays_impossible(): void
    {
        $this->assertFalse(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Held));
    }
}
