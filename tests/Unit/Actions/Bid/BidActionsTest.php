<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Bid;

use App\Actions\Bid\AcceptBidAction;
use App\Actions\Bid\ListBidsAction;
use App\Actions\Bid\PlaceBidAction;
use App\Actions\Bid\WithdrawBidAction;
use App\Actions\Task\StartWithCurrentWorkersAction;
use App\Data\Bid\PlaceBidData;
use App\Data\CursorPageData;
use App\Enums\BidStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\BidBelowMinimumException;
use App\Exceptions\Domain\CannotBidOwnTaskException;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Exceptions\Domain\TaskAlreadyDealtException;
use App\Exceptions\Domain\TaskNotBiddableException;
use App\Models\Bid;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class BidActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    private User $worker;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

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

    /**
     * Naikkan jumlah slot task fixture.
     *
     * Melamar tidak dibatasi, jadi ini BUKAN untuk memuat lebih banyak
     * pelamar. Gunanya supaya menerima satu orang belum menutup lelang —
     * dengan satu slot, penerimaan pertama langsung memindahkan task ke
     * `dealt` dan keadaan yang mau diuji tidak pernah terjadi.
     */
    private function withSlots(int $slots): void
    {
        $this->task->forceFill(['workers_needed' => $slots])->save();
    }

    private function bid(int $amount = 220_000): PlaceBidData
    {
        return new PlaceBidData(amount: $amount, message: 'Bawa alat sendiri');
    }

    public function test_it_places_a_bid(): void
    {
        $bid = app(PlaceBidAction::class)->handle($this->bid(), $this->task, $this->worker);

        $this->assertSame(220_000, $bid->amount);
        $this->assertSame(BidStatus::Pending, $bid->status);
        $this->assertSame(26, strlen((string) $bid->ulid));
    }

    public function test_it_updates_the_bids_count(): void
    {
        app(PlaceBidAction::class)->handle($this->bid(), $this->task, $this->worker);

        $this->assertSame(1, $this->task->refresh()->bids_count);
    }

    /** Satu orang satu penawaran: kirim ulang mengubah, tidak menambah. */
    public function test_bidding_again_updates_instead_of_creating(): void
    {
        $first = app(PlaceBidAction::class)->handle($this->bid(220_000), $this->task, $this->worker);
        $second = app(PlaceBidAction::class)->handle($this->bid(210_000), $this->task, $this->worker);

        $this->assertSame($first->getKey(), $second->getKey());
        $this->assertSame(210_000, $second->amount);
        $this->assertSame(1, Bid::query()->where('task_id', $this->task->getKey())->count());
    }

    public function test_re_bidding_resets_the_response_state(): void
    {
        $bid = app(PlaceBidAction::class)->handle($this->bid(), $this->task, $this->worker);
        $bid->forceFill(['status' => BidStatus::Rejected, 'responded_at' => now()])->save();

        $again = app(PlaceBidAction::class)->handle($this->bid(230_000), $this->task, $this->worker);

        $this->assertSame(BidStatus::Pending, $again->status);
        $this->assertNull($again->responded_at);
    }

    public function test_poster_cannot_bid_on_own_task(): void
    {
        try {
            app(PlaceBidAction::class)->handle($this->bid(), $this->task, $this->poster);
            $this->fail('menawar task sendiri seharusnya ditolak');
        } catch (CannotBidOwnTaskException $e) {
            $this->assertSame('cannot_bid_own_task', $e->errorCode());
            $this->assertSame(403, $e->httpStatus());
        }
    }

    public function test_bid_below_minimum_is_rejected(): void
    {
        try {
            app(PlaceBidAction::class)->handle($this->bid(100_000), $this->task, $this->worker);
            $this->fail('di bawah minimum seharusnya ditolak');
        } catch (BidBelowMinimumException $e) {
            $this->assertSame('bid_below_minimum', $e->errorCode());
            $this->assertSame(['budget_min' => 150_000], $e->context());
        }
    }

    /** budget_max SENGAJA tidak divalidasi — kebebasan memilih ada di poster. */
    public function test_bid_above_budget_max_is_allowed(): void
    {
        $this->task->forceFill(['budget_max' => 200_000])->save();

        $bid = app(PlaceBidAction::class)->handle($this->bid(500_000), $this->task->refresh(), $this->worker);

        $this->assertSame(500_000, $bid->amount);
    }

    public function test_draft_task_cannot_be_bid_on(): void
    {
        $this->task->forceFill(['status' => TaskStatus::Draft])->save();

        try {
            app(PlaceBidAction::class)->handle($this->bid(), $this->task->refresh(), $this->worker);
            $this->fail('task draft seharusnya ditolak');
        } catch (TaskNotBiddableException $e) {
            $this->assertSame('task_not_biddable', $e->errorCode());
            $this->assertSame(['status' => 'draft'], $e->context());
        }
    }

    public function test_closed_bidding_window_is_rejected(): void
    {
        $this->task->forceFill(['bidding_closes_at' => now()->subDay()])->save();

        $this->expectException(TaskNotBiddableException::class);

        app(PlaceBidAction::class)->handle($this->bid(), $this->task->refresh(), $this->worker);
    }

    public function test_future_bidding_window_is_fine(): void
    {
        $this->task->forceFill(['bidding_closes_at' => now()->addDay()])->save();

        $bid = app(PlaceBidAction::class)->handle($this->bid(), $this->task->refresh(), $this->worker);

        $this->assertSame(BidStatus::Pending, $bid->status);
    }

    public function test_withdraw_marks_the_bid_withdrawn(): void
    {
        $bid = app(PlaceBidAction::class)->handle($this->bid(), $this->task, $this->worker);

        $withdrawn = app(WithdrawBidAction::class)->handle($bid);

        $this->assertSame(BidStatus::Withdrawn, $withdrawn->status);
        $this->assertNotNull($withdrawn->responded_at);
        $this->assertSame(0, $this->task->refresh()->bids_count);
    }

    public function test_withdrawing_a_non_pending_bid_is_rejected(): void
    {
        $bid = app(PlaceBidAction::class)->handle($this->bid(), $this->task, $this->worker);
        $bid->forceFill(['status' => BidStatus::Accepted])->save();

        $this->expectException(InvalidStatusTransitionException::class);

        app(WithdrawBidAction::class)->handle($bid->refresh());
    }

    // ── DEAL ────────────────────────────────────────────────────────────────

    public function test_accepting_a_bid_seals_the_deal(): void
    {
        $bid = app(PlaceBidAction::class)->handle($this->bid(), $this->task, $this->worker);

        $task = app(AcceptBidAction::class)->handle($bid, $this->poster);

        $this->assertSame(TaskStatus::Dealt, $task->status);
        $this->assertSame(220_000, $task->agreed_amount);
        $this->assertNotNull($task->dealt_at);
        $this->assertSame(0, $task->bids_count);

        // Siapa yang mengerjakan dibaca dari penawaran yang diterima, bukan
        // dari kolom di `tasks` — satu task bisa merekrut banyak orang.
        $this->assertSame(1, $task->workers_hired);
        $this->assertSame(BidStatus::Accepted, $bid->refresh()->status);
        $this->assertSame(
            [$this->worker->getKey()],
            $task->acceptedBids()->pluck('bidder_id')->map(intval(...))->all(),
        );
    }

    /**
     * Dengan kuota = slot, mengisi slot terakhir selalu menghabiskan pelamar,
     * jadi pelamar yang menunggu hanya tersisa kalau pemberi kerja BERHENTI
     * lebih awal. Mereka harus ditutup di situ — membiarkannya pending berarti
     * orang menunggu jawaban yang tidak akan pernah datang.
     */
    public function test_stopping_early_rejects_the_applicants_left_waiting(): void
    {
        $this->withSlots(3);
        $other = $this->activeUser();
        $winner = app(PlaceBidAction::class)->handle($this->bid(220_000), $this->task, $this->worker);
        $loser = app(PlaceBidAction::class)->handle($this->bid(180_000), $this->task, $other);

        app(AcceptBidAction::class)->handle($winner, $this->poster);

        // Satu dari tiga slot terisi: lelang masih berjalan, pelamar lain
        // masih menunggu.
        $this->assertSame(TaskStatus::Open, $this->task->refresh()->status);
        $this->assertSame(BidStatus::Pending, $loser->refresh()->status);

        app(StartWithCurrentWorkersAction::class)->handle($this->task, $this->poster);

        $this->assertSame(BidStatus::Accepted, $winner->refresh()->status);
        $this->assertSame(BidStatus::Rejected, $loser->refresh()->status);
        $this->assertNotNull($loser->refresh()->responded_at);
    }

    public function test_accepting_creates_a_pending_payment(): void
    {
        $bid = app(PlaceBidAction::class)->handle($this->bid(), $this->task, $this->worker);

        app(AcceptBidAction::class)->handle($bid, $this->poster);

        $payment = Payment::query()->where('task_id', $this->task->getKey())->firstOrFail();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(220_000, $payment->amount);
        $this->assertSame($this->poster->getKey(), (int) $payment->payer_id);
    }

    public function test_accepting_increments_bids_won(): void
    {
        $bid = app(PlaceBidAction::class)->handle($this->bid(), $this->task, $this->worker);

        app(AcceptBidAction::class)->handle($bid, $this->poster);

        $this->assertSame(1, $this->reputationOf($this->worker)->bids_won);
    }

    /**
     * Menerima penawaran yang SAMA dua kali akan menaikkan jumlah pekerja
     * tanpa menambah pekerja — dan angka itu yang menentukan tagihan.
     */
    public function test_the_same_bid_cannot_be_accepted_twice(): void
    {
        $this->withSlots(2);
        $bid = app(PlaceBidAction::class)->handle($this->bid(), $this->task, $this->worker);

        app(AcceptBidAction::class)->handle($bid, $this->poster);

        $this->expectException(InvalidStatusTransitionException::class);

        try {
            app(AcceptBidAction::class)->handle($bid->refresh(), $this->poster);
        } finally {
            $this->assertSame(1, $this->task->refresh()->workers_hired);
            $this->assertSame(220_000, $this->task->refresh()->agreed_amount);
        }
    }

    /**
     * Task satu orang: menerima seorang pelamar langsung menutup lelang, dan
     * pelamar lain — yang boleh sebanyak apa pun — ikut ditolak.
     */
    public function test_accepting_on_a_single_slot_task_closes_the_auction(): void
    {
        $other = $this->activeUser();
        $winner = app(PlaceBidAction::class)->handle($this->bid(220_000), $this->task, $this->worker);
        $loser = app(PlaceBidAction::class)->handle($this->bid(180_000), $this->task, $other);

        $task = app(AcceptBidAction::class)->handle($winner, $this->poster);

        $this->assertSame(TaskStatus::Dealt, $task->status);
        $this->assertSame(BidStatus::Rejected, $loser->refresh()->status);

        try {
            app(AcceptBidAction::class)->handle($loser->refresh(), $this->poster);
            $this->fail('deal kedua seharusnya ditolak');
        } catch (TaskAlreadyDealtException $e) {
            $this->assertSame(409, $e->httpStatus());
            $this->assertSame('task_already_dealt', $e->errorCode());
        }
    }

    public function test_accepting_on_a_non_open_task_is_rejected(): void
    {
        $bid = app(PlaceBidAction::class)->handle($this->bid(), $this->task, $this->worker);
        $this->task->forceFill(['status' => TaskStatus::Cancelled])->save();

        $this->expectException(TaskNotBiddableException::class);

        app(AcceptBidAction::class)->handle($bid, $this->poster);
    }

    // ── daftar ──────────────────────────────────────────────────────────────

    public function test_list_bids_sorted_by_amount(): void
    {
        $other = $this->activeUser();
        app(PlaceBidAction::class)->handle($this->bid(220_000), $this->task, $this->worker);
        app(PlaceBidAction::class)->handle($this->bid(180_000), $this->task, $other);

        $page = app(ListBidsAction::class)->forTask($this->task, new CursorPageData(20), 'amount');

        $this->assertSame([180_000, 220_000], $page->pluck('amount')->all());
    }

    public function test_list_bids_sorted_by_rating(): void
    {
        $other = $this->activeUser();
        $this->worker->workerProfileOrCreate()
            ->forceFill(['worker_rating_avg' => 4.9, 'tasks_completed' => 214])->save();
        app(PlaceBidAction::class)->handle($this->bid(220_000), $this->task, $this->worker);
        app(PlaceBidAction::class)->handle($this->bid(180_000), $this->task, $other);

        $page = app(ListBidsAction::class)->forTask($this->task, new CursorPageData(20), 'rating');

        $this->assertSame($this->worker->getKey(), (int) $page->first()->bidder_id);
    }

    public function test_list_bids_sorted_by_newest(): void
    {
        $other = $this->activeUser();
        app(PlaceBidAction::class)->handle($this->bid(220_000), $this->task, $this->worker);
        app(PlaceBidAction::class)->handle($this->bid(180_000), $this->task, $other);

        $page = app(ListBidsAction::class)->forTask($this->task, new CursorPageData(20), 'newest');

        $this->assertCount(2, $page->items());
    }

    public function test_list_bids_loads_the_verification_count(): void
    {
        app(PlaceBidAction::class)->handle($this->bid(), $this->task, $this->worker);

        $page = app(ListBidsAction::class)->forTask($this->task, new CursorPageData(20));

        $this->assertSame(0, (int) $page->first()->bidder->identity_verified_count);
    }

    public function test_list_bids_by_bidder(): void
    {
        app(PlaceBidAction::class)->handle($this->bid(), $this->task, $this->worker);

        $page = app(ListBidsAction::class)->byBidder($this->worker, new CursorPageData(20));

        $this->assertCount(1, $page->items());
        $this->assertNotNull($page->first()->task);
    }
}
