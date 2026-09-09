<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Task;

use App\Actions\Bid\AcceptBidAction;
use App\Actions\Bid\PlaceBidAction;
use App\Actions\Payment\HoldPaymentAction;
use App\Actions\Task\StartWithCurrentWorkersAction;
use App\Data\Bid\PlaceBidData;
use App\Enums\BidStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Exceptions\Domain\NoWorkersHiredException;
use App\Exceptions\Domain\TaskAlreadyDealtException;
use App\Exceptions\Domain\TaskNotBiddableException;
use App\Models\Bid;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Satu task merekrut banyak orang.
 *
 * Dua angka yang harus dipegang sepanjang kelas ini, karena keduanya berasal
 * dari `workers_needed` yang sama:
 *
 *   - berapa orang yang akan direkrut (slot), dan
 *   - berapa lamaran yang boleh masuk (kuota).
 *
 * Konsekuensi yang sengaja diuji: menolak seorang pelamar TIDAK membuka kembali
 * tempatnya, menarik lamaran sendiri MEMBUKA, dan mengisi slot terakhir
 * menutup lelang.
 */
final class MultiWorkerHiringTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->poster = $this->activeUser();
        $this->task = $this->taskNeeding(3);
    }

    private function taskNeeding(int $workers): Task
    {
        return Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Open,
            'budget_min' => 100_000,
            'budget_max' => null,
            'workers_needed' => $workers,
        ]);
    }

    private function apply(User $worker, int $amount = 150_000, ?Task $task = null): Bid
    {
        return app(PlaceBidAction::class)->handle(
            new PlaceBidData(amount: $amount, message: 'siap'),
            $task ?? $this->task,
            $worker,
        );
    }

    private function accept(Bid $bid): Task
    {
        return app(AcceptBidAction::class)->handle($bid, $this->poster);
    }

    // ── Lelang tetap terbuka ────────────────────────────────────────────────

    /**
     * `workers_needed` adalah berapa orang yang DITERIMA, bukan berapa yang
     * boleh melamar. Pelamarnya boleh jauh lebih banyak daripada slotnya —
     * justru dari situ pemberi kerja punya sesuatu untuk dipilih.
     */
    public function test_far_more_people_may_apply_than_there_are_slots(): void
    {
        foreach (range(1, 8) as $i) {
            $this->apply($this->activeUser(), 100_000 + $i * 1_000);
        }

        $task = $this->task->refresh();

        $this->assertSame(8, $task->bids()->count());
        $this->assertSame(8, $task->bids_count);
        $this->assertSame(3, $task->workers_needed, 'slot tidak ikut membesar');
        $this->assertSame(TaskStatus::Open, $task->status);
    }

    /** Task satu orang tetap sebuah lelang: banyak yang melamar, satu terpilih. */
    public function test_a_single_worker_task_is_still_an_auction(): void
    {
        $solo = $this->taskNeeding(1);

        foreach (range(1, 5) as $i) {
            $this->apply($this->activeUser(), 100_000 + $i * 1_000, $solo);
        }

        $this->assertSame(5, $solo->bids()->count());
    }

    /** Mengubah tawaran sendiri menulis baris yang sama, bukan pelamar baru. */
    public function test_changing_your_own_bid_replaces_it(): void
    {
        $worker = $this->activeUser();

        $first = $this->apply($worker, 150_000);
        $again = $this->apply($worker, 175_000);

        $this->assertSame($first->getKey(), $again->getKey());
        $this->assertSame(175_000, $again->amount);
        $this->assertSame(1, $this->task->bids()->count());
    }

    /**
     * Pemberi kerja memilih berdasar harga, bukan urutan datang — itu inti
     * lelangnya. Yang diuji di sini: penawaran termurah bisa dipilih meski
     * datang terakhir, dan yang lain tidak ikut terbawa.
     */
    public function test_the_poster_picks_by_price_not_by_arrival(): void
    {
        $mahal = $this->apply($this->activeUser(), 400_000);
        $murah = $this->apply($this->activeUser(), 120_000);

        $this->accept($murah);

        $this->assertSame(BidStatus::Accepted, $murah->refresh()->status);
        $this->assertSame(BidStatus::Pending, $mahal->refresh()->status);
        $this->assertSame(120_000, $this->task->refresh()->agreed_amount);
    }

    /**
     * Mengisi slot TERAKHIR menutup lelang, dan pelamar yang masih menunggu
     * harus ditutup di situ juga — membiarkannya pending berarti orang
     * menunggu jawaban yang tidak akan pernah datang.
     */
    public function test_filling_the_last_slot_rejects_everyone_still_waiting(): void
    {
        $applicants = collect(range(1, 6))->map(fn (int $i): Bid => $this->apply(
            $this->activeUser(),
            100_000 + $i * 1_000,
        ));

        foreach ([0, 1, 2] as $i) {
            $this->accept($applicants[$i]);
        }

        $this->assertSame(TaskStatus::Dealt, $this->task->refresh()->status);

        foreach ([3, 4, 5] as $i) {
            $this->assertSame(BidStatus::Rejected, $applicants[$i]->refresh()->status);
            $this->assertNotNull($applicants[$i]->refresh()->responded_at);
        }

        $this->assertSame(0, $this->task->bids_count);
    }

    /** Sesudah slot habis, tidak ada lagi yang bisa diterima. */
    public function test_nobody_can_be_hired_after_the_slots_are_full(): void
    {
        $extra = $this->apply($this->activeUser(), 130_000);

        foreach (range(1, 3) as $i) {
            $this->accept($this->apply($this->activeUser(), 100_000 + $i));
        }

        try {
            $this->accept($extra->refresh());
            $this->fail('perekrutan melebihi slot seharusnya ditolak');
        } catch (TaskAlreadyDealtException $e) {
            $this->assertSame('task_already_dealt', $e->errorCode());
            $this->assertSame(409, $e->httpStatus());
        } finally {
            $this->assertSame(3, $this->task->refresh()->workers_hired);
        }
    }

    // ── Mengisi slot ────────────────────────────────────────────────────────

    public function test_hiring_fills_one_slot_at_a_time_and_the_auction_stays_open(): void
    {
        $bids = collect(range(1, 3))->map(fn (int $i): Bid => $this->apply(
            $this->activeUser(),
            100_000 * $i,
        ));

        $this->accept($bids[0]);
        $this->assertSame(1, $this->task->refresh()->workers_hired);
        $this->assertSame(TaskStatus::Open, $this->task->status, 'masih ada slot kosong');
        $this->assertSame(2, $this->task->slotsRemaining());
        $this->assertNull($this->task->dealt_at);

        $this->accept($bids[1]);
        $this->assertSame(2, $this->task->refresh()->workers_hired);
        $this->assertSame(TaskStatus::Open, $this->task->status);

        // Slot terakhir: perekrutan selesai.
        $this->accept($bids[2]);
        $task = $this->task->refresh();

        $this->assertSame(3, $task->workers_hired);
        $this->assertSame(0, $task->slotsRemaining());
        $this->assertSame(TaskStatus::Dealt, $task->status);
        $this->assertNotNull($task->dealt_at);
        $this->assertSame(0, $task->bids_count);
    }

    /** Tagihan satu baris, sebesar jumlah SELURUH penawaran yang diterima. */
    public function test_the_bill_is_the_sum_of_every_accepted_bid(): void
    {
        foreach ([100_000, 250_000, 400_000] as $amount) {
            $this->accept($this->apply($this->activeUser(), $amount));
        }

        $task = $this->task->refresh();
        $payment = Payment::query()->where('task_id', $task->getKey())->sole();

        $this->assertSame(750_000, $task->agreed_amount);
        $this->assertSame(750_000, $payment->amount);
        $this->assertSame(PaymentStatus::Pending, $payment->status);

        // Satu tagihan, bukan satu per pekerja: pemberi kerja transfer sekali.
        $this->assertSame(1, Payment::query()->where('task_id', $task->getKey())->count());
    }

    public function test_every_hired_worker_is_readable_from_the_task(): void
    {
        $hired = collect(range(1, 3))->map(function () {
            $worker = $this->activeUser();
            $this->accept($this->apply($worker));

            return $worker->getKey();
        })->all();

        $this->assertSame($hired, $this->task->refresh()->workers()->pluck('users.id')
            ->map(intval(...))->all());
        $this->assertCount(3, $this->task->acceptedBids()->get());
    }

    /** Penghitung ditulis ke BARIS-nya, bukan cuma ke model di memori. */
    public function test_the_counters_are_persisted(): void
    {
        $this->accept($this->apply($this->activeUser(), 175_000));

        $row = DB::table('tasks')->where('id', $this->task->getKey())->first();

        $this->assertSame(3, (int) $row->workers_needed);
        $this->assertSame(1, (int) $row->workers_hired);
        $this->assertSame(175_000, (int) $row->agreed_amount);
    }

    // ── Mulai lebih awal ────────────────────────────────────────────────────

    public function test_starting_early_locks_the_target_to_who_was_actually_hired(): void
    {
        $hired = $this->apply($this->activeUser(), 150_000);
        $waiting = $this->apply($this->activeUser(), 160_000);
        $this->accept($hired);

        $task = app(StartWithCurrentWorkersAction::class)->handle($this->task, $this->poster);

        // Target diturunkan, bukan sekadar "dipaksa deal": kalau dibiarkan di
        // 3, task ini akan selamanya terlihat kekurangan dua orang.
        $this->assertSame(1, $task->workers_needed);
        $this->assertSame(1, $task->workers_hired);
        $this->assertSame(0, $task->slotsRemaining());
        $this->assertSame(TaskStatus::Dealt, $task->status);

        // Pelamar yang menunggu ditutup — bukan dibiarkan menggantung.
        $this->assertSame(BidStatus::Rejected, $waiting->refresh()->status);
        $this->assertNotNull($waiting->refresh()->responded_at);
    }

    public function test_starting_with_nobody_hired_is_refused(): void
    {
        $this->apply($this->activeUser());

        try {
            app(StartWithCurrentWorkersAction::class)->handle($this->task, $this->poster);
            $this->fail('mulai tanpa pekerja seharusnya ditolak');
        } catch (NoWorkersHiredException $e) {
            $this->assertSame('no_workers_hired', $e->errorCode());
        } finally {
            // Tidak boleh ada yang berubah pada percobaan yang gagal.
            $task = $this->task->refresh();
            $this->assertSame(TaskStatus::Open, $task->status);
            $this->assertSame(3, $task->workers_needed);
            $this->assertNull($task->dealt_at);
        }
    }

    public function test_starting_a_task_that_is_no_longer_open_is_refused(): void
    {
        $this->accept($this->apply($this->activeUser()));
        $this->task->forceFill(['status' => TaskStatus::Cancelled])->save();

        $this->expectException(TaskNotBiddableException::class);

        app(StartWithCurrentWorkersAction::class)->handle($this->task, $this->poster);
    }

    // ── Dana ditahan membuka satu activity per pekerja ───────────────────────

    public function test_one_transfer_opens_one_activity_per_worker(): void
    {
        $amounts = [120_000, 240_000, 360_000];
        $workers = [];

        foreach ($amounts as $amount) {
            $worker = $this->activeUser();
            $workers[] = $worker->getKey();
            $this->accept($this->apply($worker, $amount));
        }

        $activities = app(HoldPaymentAction::class)->handle($this->task->refresh(), $this->poster);

        $this->assertCount(3, $activities);
        $this->assertSame($workers, $activities->pluck('worker_id')->map(intval(...))->all());

        // Harga PER ORANG, bukan total task — kalau total, setiap pekerja
        // terlihat berhak atas seluruh dana.
        $this->assertSame($amounts, $activities->pluck('agreed_amount')->map(intval(...))->all());

        // Semua terbuka oleh SATU pembayaran yang sama.
        $this->assertCount(1, $activities->pluck('payment_id')->unique());
        $this->assertSame(TaskStatus::Active, $this->task->refresh()->status);
    }

    /**
     * Endpoint ini nantinya dipicu webhook gateway, yang bisa mengirim ulang.
     * Panggilan kedua tidak boleh menggandakan activity siapa pun.
     */
    public function test_a_repeated_hold_never_duplicates_an_activity(): void
    {
        $task = $this->taskNeeding(2);

        foreach (range(1, 2) as $i) {
            $this->accept($this->apply($this->activeUser(), 100_000 * $i, $task));
        }

        app(HoldPaymentAction::class)->handle($task->refresh(), $this->poster);

        try {
            app(HoldPaymentAction::class)->handle($task->refresh(), $this->poster);
        } catch (\Throwable) {
            // Pembayaran sudah held; yang diperiksa di sini jumlah barisnya.
        }

        $this->assertSame(2, $task->activities()->count());
    }

    /**
     * Tagihan sudah ada sejak pelamar pertama diterima, jadi tanpa penjaga
     * pemberi kerja bisa menahan dana ketika masih ada slot kosong — dan
     * pekerja yang belum direkrut tidak akan pernah punya activity.
     */
    public function test_money_cannot_be_held_while_slots_are_still_open(): void
    {
        $this->accept($this->apply($this->activeUser()));

        $this->assertSame(TaskStatus::Open, $this->task->refresh()->status);

        try {
            app(HoldPaymentAction::class)->handle($this->task->refresh(), $this->poster);
            $this->fail('menahan dana sebelum perekrutan selesai seharusnya ditolak');
        } catch (InvalidStatusTransitionException $e) {
            $this->assertSame('invalid_status_transition', $e->errorCode());
        } finally {
            // Tidak ada yang tertulis pada percobaan yang gagal.
            $this->assertSame(0, $this->task->activities()->count());
            $this->assertSame(
                PaymentStatus::Pending,
                Payment::query()->where('task_id', $this->task->getKey())->sole()->status,
            );
        }
    }
}
