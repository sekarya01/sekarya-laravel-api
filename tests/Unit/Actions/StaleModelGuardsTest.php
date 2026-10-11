<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Bid\AcceptBidAction;
use App\Actions\Bid\PlaceBidAction;
use App\Actions\Bid\WithdrawBidAction;
use App\Actions\Task\CancelTaskAction;
use App\Actions\Task\ExpireBiddingAction;
use App\Actions\Task\UpdateTaskAction;
use App\Data\Bid\PlaceBidData;
use App\Data\Task\UpdateTaskData;
use App\Enums\BidStatus;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Exceptions\Domain\TaskNotBiddableException;
use App\Exceptions\Domain\TaskNotCancellableException;
use App\Exceptions\Domain\TaskNotEditableException;
use App\Http\Requests\Api\V1\Task\UpdateTaskRequest;
use App\Models\Bid;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cek-lalu-ubah di jalur uang & status wajib memeriksa baris yang DIKUNCI,
 * bukan model yang dibaca sebelum transaksi.
 *
 * Balapan sungguhan tidak bisa diputar di PHPUnit, jadi tiap test meniru
 * "permintaan lain sudah commit lebih dulu": baris di basis data diubah,
 * lalu Action diberi model lama yang masih memegang keadaan sebelumnya.
 * Action yang benar membaca ulang di bawah `lockForUpdate()` dan menolak.
 */
final class StaleModelGuardsTest extends TestCase
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
            'workers_needed' => 2,
        ]);
    }

    private function placeBid(?User $bidder = null): Bid
    {
        return app(PlaceBidAction::class)->handle(
            new PlaceBidData(amount: 200_000),
            $this->task,
            $bidder ?? $this->worker,
        );
    }

    private function setStatusBehindTheModel(Task|Bid $model, TaskStatus|BidStatus $status): void
    {
        $model::query()->whereKey($model->getKey())->update(['status' => $status->value]);
    }

    public function test_withdrawing_a_bid_that_was_just_accepted_is_rejected(): void
    {
        $stale = $this->placeBid();
        $this->setStatusBehindTheModel($stale, BidStatus::Accepted);

        $this->expectException(InvalidStatusTransitionException::class);

        try {
            app(WithdrawBidAction::class)->handle($stale);
        } finally {
            $this->assertSame(BidStatus::Accepted, $stale->fresh()->status);
        }
    }

    public function test_accepting_a_bid_that_was_just_withdrawn_is_rejected(): void
    {
        $stale = $this->placeBid();
        $this->setStatusBehindTheModel($stale, BidStatus::Withdrawn);

        $this->expectException(InvalidStatusTransitionException::class);

        try {
            app(AcceptBidAction::class)->handle($stale, $this->poster);
        } finally {
            $this->assertSame(0, (int) $this->task->fresh()->workers_hired);
            $this->assertSame(BidStatus::Withdrawn, $stale->fresh()->status);
        }
    }

    public function test_bidding_on_a_task_that_was_just_dealt_is_rejected(): void
    {
        $stale = $this->task;
        $this->setStatusBehindTheModel($stale, TaskStatus::Dealt);

        $this->expectException(TaskNotBiddableException::class);

        try {
            app(PlaceBidAction::class)->handle(new PlaceBidData(amount: 200_000), $stale, $this->worker);
        } finally {
            $this->assertSame(0, Bid::query()->count());
        }
    }

    /**
     * Bukan balapan, tapi celah yang sama keluarganya: pekerja yang SUDAH
     * diterima menawar lagi, dan `updateOrCreate` dulu mengembalikan bid-nya
     * ke `pending` — `workers_hired` dan escrow tidak lagi cocok dengan bids.
     */
    public function test_an_accepted_worker_cannot_reset_the_bid_by_bidding_again(): void
    {
        $bid = $this->placeBid();
        app(AcceptBidAction::class)->handle($bid, $this->poster);

        $this->expectException(InvalidStatusTransitionException::class);

        try {
            $this->placeBid();
        } finally {
            $this->assertSame(BidStatus::Accepted, $bid->fresh()->status);
            $this->assertSame(1, (int) $this->task->fresh()->workers_hired);
        }
    }

    public function test_cancelling_a_task_that_was_just_completed_is_rejected(): void
    {
        $stale = $this->task;
        $this->setStatusBehindTheModel($stale, TaskStatus::Completed);

        $this->expectException(TaskNotCancellableException::class);

        try {
            app(CancelTaskAction::class)->handle($stale, $this->poster);
        } finally {
            $this->assertSame(TaskStatus::Completed, $stale->fresh()->status);
        }
    }

    public function test_editing_a_task_that_was_just_dealt_is_rejected(): void
    {
        $stale = $this->task;
        $this->setStatusBehindTheModel($stale, TaskStatus::Dealt);
        $data = UpdateTaskData::fromRequest(UpdateTaskRequest::create('/', 'PUT', ['title' => 'Judul baru']));

        $this->expectException(TaskNotEditableException::class);

        try {
            app(UpdateTaskAction::class)->handle($stale, $data);
        } finally {
            $this->assertNotSame('Judul baru', $stale->fresh()->title);
        }
    }

    /**
     * Sweep membaca satu potongan task, lalu memprosesnya satu per satu. Task
     * yang diterima penawarannya DI ANTARA keduanya tidak boleh ditimpa
     * `expired` dari salinan lama.
     */
    public function test_the_expiry_sweep_skips_a_task_dealt_after_it_was_read(): void
    {
        $this->task->forceFill(['bidding_closes_at' => now()->subMinute()])->save();

        $fired = false;
        Task::retrieved(function (Task $task) use (&$fired): void {
            if (! $fired && $task->is($this->task)) {
                $fired = true;
                Task::query()->whereKey($task->getKey())->update(['status' => TaskStatus::Dealt->value]);
            }
        });

        $closed = app(ExpireBiddingAction::class)->handle();

        $this->assertSame(0, $closed);
        $this->assertSame(TaskStatus::Dealt, $this->task->fresh()->status);
    }
}
