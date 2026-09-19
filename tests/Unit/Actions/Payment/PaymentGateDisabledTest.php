<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Payment;

use App\Actions\Activity\ApproveActivityAction;
use App\Actions\Activity\StartActivityAction;
use App\Actions\Activity\SubmitActivityAction;
use App\Actions\Bid\AcceptBidAction;
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
use App\Support\WalletLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Alur pekerjaan selagi gerbang pembayaran DIMATIKAN.
 *
 * Yang dijaga saklar ini tinggal dua: MULAI BEKERJA dan pembayaran upahnya.
 * Keberadaan activity sudah tidak bergantung padanya — deal selalu membuka
 * pekerjaan, dan itu diuji DealOpensWorkTest dengan gerbang hidup.
 *
 * Selama mati, tidak ada tagihan yang pernah beranjak dari `pending`, jadi:
 *
 *  1. `DepartActivityAction` dan `StartActivityAction` tidak menuntut `held` —
 *     kalau menuntut, tidak ada pekerjaan yang pernah bisa dijalani.
 *  2. Persetujuan hasil tidak melepas tagihan DAN tidak mengkreditkan upah.
 *     Pekerjaannya tetap ditutup; yang tertunda uangnya.
 *
 * Pembayaran tidak pernah dipalsukan jadi `held`, dan `pending → held` tetap
 * mustahil: yang dilewati PEMERIKSAAN, bukan CATATAN.
 *
 * Suite berjalan dengan gerbang hidup (phpunit.xml), jadi setiap test di sini
 * mematikannya sendiri.
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

    private function ledger(): WalletLedger
    {
        return app(WalletLedger::class);
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

    /** Yang dilewati pemeriksaannya, bukan catatannya. */
    public function test_payment_is_not_faked_as_held(): void
    {
        $this->accept();

        $payment = $this->task->refresh()->payment;

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->paid_at);
        $this->assertNull($payment->held_at);
    }

    public function test_worker_can_start_without_money_being_held(): void
    {
        $this->accept();
        $activity = Activity::query()->where('task_id', $this->task->getKey())->sole();

        $started = app(StartActivityAction::class)->handle($this->bringToSite($activity));

        $this->assertSame(ActivityStatus::InProgress, $started->status);
        $this->assertSame(PaymentStatus::Pending, $started->payment->refresh()->status);
    }

    /** Ujung ke ujung: terima → mulai → serahkan → setujui. */
    public function test_the_whole_flow_closes_without_a_payment(): void
    {
        $this->accept();
        $activity = Activity::query()->where('task_id', $this->task->getKey())->sole();

        app(StartActivityAction::class)->handle($this->bringToSite($activity));
        app(SubmitActivityAction::class)->handle(
            new SubmitActivityData('Sudah beres', []),
            $activity->refresh(),
        );
        $this->assertSame(TaskStatus::Submitted, $this->task->refresh()->status);

        app(ApproveActivityAction::class)->handle($activity->refresh(), $this->poster);

        $this->assertSame(TaskStatus::Completed, $this->task->refresh()->status);
        $this->assertNotNull($this->task->refresh()->completed_at);
        $this->assertSame(ActivityStatus::Approved, $activity->refresh()->status);
        // Reputasinya tetap tumbuh: orangnya memang sudah menyelesaikan bagiannya.
        $this->assertSame(1, (int) $this->worker->workerProfileOrCreate()->refresh()->tasks_completed);
    }

    /**
     * Tidak ada dana yang masuk, jadi tidak ada yang keluar.
     *
     * Baik pelepasan tagihan maupun kredit upahnya ditahan. Mengkreditkan
     * upahnya saja "supaya alurnya terasa tuntas" bukan pilihan: saldo itu bisa
     * ditarik lewat POST /me/wallet/withdrawals, jadi ia akan jadi tagihan
     * sungguhan atas uang yang tidak pernah ada.
     */
    public function test_nothing_is_paid_out_without_a_payment(): void
    {
        $this->accept();
        $activity = Activity::query()->where('task_id', $this->task->getKey())->sole();

        app(StartActivityAction::class)->handle($this->bringToSite($activity));
        app(SubmitActivityAction::class)->handle(
            new SubmitActivityData('Sudah beres', []),
            $activity->refresh(),
        );
        app(ApproveActivityAction::class)->handle($activity->refresh(), $this->poster);

        $payment = $this->task->refresh()->payment;
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNull($payment->released_at);

        $this->assertSame(0, WalletEntry::query()
            ->where('type', WalletEntryType::Earning)
            ->where('reference_id', $activity->getKey())
            ->count());
        $this->assertSame(0, (int) $this->ledger()->walletFor($this->worker->refresh())->balance);

        // Jejaknya tidak mengaku-aku: task selesai, dana belum dilepas.
        $this->assertStringContainsString(
            'tidak ada yang dilepas',
            (string) TaskStatusLog::query()
                ->where('task_id', $this->task->getKey())
                ->where('to_status', TaskStatus::Completed->value)
                ->value('reason'),
        );
    }

    /** Gerbang mati melewati pemeriksaan, bukan melonggarkan aturannya. */
    public function test_pending_to_held_stays_impossible(): void
    {
        $this->assertFalse(PaymentStatus::Pending->canTransitionTo(PaymentStatus::Held));
    }
}
