<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Activity;

use App\Actions\Activity\ConfirmArrivalAction;
use App\Actions\Activity\DepartActivityAction;
use App\Actions\Activity\StartActivityAction;
use App\Enums\ActivityStatus;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Exceptions\Domain\PaymentNotHeldException;
use App\Models\Activity;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Perjalanan ke lokasi: berangkat (pekerja) lalu tiba (PEMBERI KERJA).
 *
 * Yang dijaga kelas ini bukan urutannya, melainkan kepemilikan langkahnya.
 * Pekerja mengumumkan keberangkatan — cuma dia yang tahu ia sudah jalan.
 * Kedatangan diakui pemberi kerja, karena yang melihat orangnya berdiri di
 * depan pintu adalah tuan rumah. Kalau yang datang boleh menyatakan sendiri
 * ia tiba, "sudah sampai" berhenti berarti apa pun.
 */
final class ActivityTravelTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    private User $worker;

    private Task $task;

    private Activity $activity;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->poster = $this->activeUser();
        $this->worker = $this->activeUser();
        $this->task = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Dealt,
        ]);
        $this->hireWorker($this->task, $this->worker, 220_000);
        Payment::factory()->create([
            'task_id' => $this->task->getKey(),
            'payer_id' => $this->poster->getKey(),
            'amount' => 220_000,
        ]);

        $this->activity = $this->openActivities($this->task, $this->poster)->sole();
    }

    public function test_departing_marks_the_worker_on_the_way(): void
    {
        $departed = app(DepartActivityAction::class)->handle($this->activity);

        $this->assertSame(ActivityStatus::OnTheWay, $departed->status);
        $this->assertNotNull($departed->departed_at);
        $this->assertNull($departed->arrived_at);
    }

    public function test_arrival_is_recorded_separately(): void
    {
        app(DepartActivityAction::class)->handle($this->activity);

        $arrived = app(ConfirmArrivalAction::class)->handle($this->activity->refresh());

        $this->assertSame(ActivityStatus::Arrived, $arrived->status);
        $this->assertNotNull($arrived->departed_at);
        $this->assertNotNull($arrived->arrived_at);
        // Tiba bukan mulai bekerja: ketiganya menjawab pertanyaan berbeda.
        $this->assertNull($arrived->started_at);
    }

    /** Pekerjaan tidak dimulai dari perjalanan. */
    public function test_starting_before_arriving_is_rejected(): void
    {
        app(DepartActivityAction::class)->handle($this->activity);

        $this->expectException(InvalidStatusTransitionException::class);

        app(StartActivityAction::class)->handle($this->activity->refresh());
    }

    /** Dan tidak dimulai dari diam di rumah. */
    public function test_starting_without_leaving_is_rejected(): void
    {
        $this->expectException(InvalidStatusTransitionException::class);

        app(StartActivityAction::class)->handle($this->activity);
    }

    public function test_arriving_without_departing_is_rejected(): void
    {
        $this->expectException(InvalidStatusTransitionException::class);

        app(ConfirmArrivalAction::class)->handle($this->activity);
    }

    public function test_departing_twice_is_rejected(): void
    {
        app(DepartActivityAction::class)->handle($this->activity);

        $this->expectException(InvalidStatusTransitionException::class);

        app(DepartActivityAction::class)->handle($this->activity->refresh());
    }

    public function test_after_arriving_the_work_can_start(): void
    {
        $started = app(StartActivityAction::class)->handle($this->bringToSite($this->activity));

        $this->assertSame(ActivityStatus::InProgress, $started->status);
        $this->assertNotNull($started->started_at);
        // Jejak perjalanannya tetap utuh sesudah pekerjaan berjalan.
        $this->assertNotNull($started->departed_at);
        $this->assertNotNull($started->arrived_at);
    }

    /**
     * Berangkat pun dijaga dana.
     *
     * Berangkat adalah waktu dan ongkos yang sudah dikeluarkan pekerja, jadi
     * ia tidak boleh diminta bergerak atas tagihan yang belum dipastikan.
     */
    public function test_departing_rechecks_that_money_is_still_held(): void
    {
        $this->activity->payment->forceFill([
            'status' => PaymentStatus::Refunded,
            'refunded_at' => now(),
        ])->save();

        try {
            app(DepartActivityAction::class)->handle($this->activity->refresh());
            $this->fail('dana yang sudah dikembalikan seharusnya menghalangi');
        } catch (PaymentNotHeldException $e) {
            $this->assertSame('payment_not_held', $e->errorCode());
            $this->assertSame(['payment_status' => 'refunded'], $e->context());
        }
    }

    /**
     * Mengakui kedatangan TIDAK diperiksa dananya.
     *
     * Orangnya sudah berdiri di sana; menolak pengakuan itu karena tagihan
     * belum beres tidak membuatnya pulang, hanya menghapus catatannya.
     */
    public function test_confirming_arrival_does_not_depend_on_the_money(): void
    {
        app(DepartActivityAction::class)->handle($this->activity);
        $this->activity->payment->forceFill([
            'status' => PaymentStatus::Refunded,
            'refunded_at' => now(),
        ])->save();

        $arrived = app(ConfirmArrivalAction::class)->handle($this->activity->refresh());

        $this->assertSame(ActivityStatus::Arrived, $arrived->status);
    }
}
