<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Payment;

use App\Actions\Admin\Payment\ConfirmPaymentAction;
use App\Actions\Admin\Payment\RejectPaymentAction;
use App\Actions\Payment\ReportTransferAction;
use App\Data\Admin\RejectPaymentData;
use App\Enums\ActivityStatus;
use App\Enums\ActorType;
use App\Enums\AdminAction;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Activity;
use App\Models\Admin;
use App\Models\AdminAuditLog;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TaskStatusLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Laporan transfer (pemberi kerja) dan konfirmasinya (pengelola).
 *
 * Menggantikan HoldPaymentActionTest. Yang paling penting di kelas ini bukan
 * jalur bahagianya, tapi satu invarian baru: TIDAK ADA jalan dari `pending` ke
 * `held` selain lewat pengelola. Sebelumnya pemberi kerja punya jalan itu
 * sendiri, yang berarti ia menyatakan sendiri uangnya sudah masuk.
 */
final class PaymentConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    private User $worker;

    private Admin $admin;

    private Task $task;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->poster = $this->activeUser();
        $this->worker = $this->activeUser();
        $this->admin = $this->activeAdmin();
        $this->task = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Dealt,
            'dealt_at' => now(),
        ]);
        $this->hireWorker($this->task, $this->worker, 220_000);
        $this->payment = Payment::factory()->create([
            'task_id' => $this->task->getKey(),
            'payer_id' => $this->poster->getKey(),
            'amount' => 220_000,
        ]);
    }

    private function report(): Payment
    {
        return app(ReportTransferAction::class)->handle($this->task, $this->poster);
    }

    private function confirm(): void
    {
        app(ConfirmPaymentAction::class)->handle($this->payment->refresh(), $this->admin, '10.0.0.7');
    }

    // ── Laporan pemberi kerja ───────────────────────────────────────────────

    public function test_reporting_a_transfer_does_not_hold_the_money(): void
    {
        $this->report();

        $payment = $this->payment->refresh();

        $this->assertSame(PaymentStatus::AwaitingConfirmation, $payment->status);
        $this->assertNotNull($payment->reported_at);
        $this->assertFalse($payment->status->opensActivity());

        // Yang menjaga pekerja: laporan saja tidak membuka pekerjaan apa pun.
        $this->assertNull($payment->held_at);
        $this->assertNull($payment->paid_at);
        $this->assertSame(0, Activity::query()->where('task_id', $this->task->getKey())->count());
        $this->assertSame(TaskStatus::Dealt, $this->task->refresh()->status);
    }

    public function test_a_task_still_hiring_cannot_be_reported(): void
    {
        $open = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Open,
        ]);
        Payment::factory()->create([
            'task_id' => $open->getKey(),
            'payer_id' => $this->poster->getKey(),
        ]);

        $this->expectException(InvalidStatusTransitionException::class);

        app(ReportTransferAction::class)->handle($open, $this->poster);
    }

    // ── Konfirmasi pengelola ────────────────────────────────────────────────

    /** INVARIAN INTI: tanpa laporan, tidak ada yang bisa ditahan. */
    public function test_a_payment_that_was_never_reported_cannot_be_confirmed(): void
    {
        $this->assertSame(PaymentStatus::Pending, $this->payment->status);

        try {
            $this->confirm();
            $this->fail('konfirmasi tanpa laporan seharusnya ditolak');
        } catch (InvalidStatusTransitionException $e) {
            $this->assertSame(['from' => 'pending', 'to' => 'held'], $e->context());
        }

        $this->assertSame(0, Activity::query()->where('task_id', $this->task->getKey())->count());
    }

    public function test_confirming_holds_the_money(): void
    {
        $this->report();
        $this->confirm();

        $payment = $this->payment->refresh();

        $this->assertSame(PaymentStatus::Held, $payment->status);
        $this->assertNotNull($payment->paid_at);
        $this->assertNotNull($payment->held_at);
        $this->assertTrue($payment->status->opensActivity());
    }

    public function test_confirming_opens_the_activity(): void
    {
        $this->report();

        $activity = app(ConfirmPaymentAction::class)
            ->handle($this->payment->refresh(), $this->admin)
            ->sole();

        $this->assertSame(ActivityStatus::Open, $activity->status);
        $this->assertNotNull($activity->opened_at);
        $this->assertSame($this->worker->getKey(), (int) $activity->worker_id);
        $this->assertSame($this->payment->getKey(), (int) $activity->payment_id);
        $this->assertSame(220_000, $activity->agreed_amount);
        $this->assertSame(26, strlen((string) $activity->ulid));
    }

    public function test_confirming_moves_the_task_to_active(): void
    {
        $this->report();
        $this->confirm();

        $this->assertSame(TaskStatus::Active, $this->task->refresh()->status);
    }

    /**
     * Jejak status task harus menyebut PENGELOLA sebagai pelakunya.
     *
     * `task_status_logs` menyimpan actor_type + actor_id tanpa foreign key,
     * jadi id pengelola yang tercatat dengan actor_type salah akan terbaca
     * sebagai pengguna dengan id yang sama — orang lain sama sekali.
     */
    public function test_the_status_log_names_the_admin_as_actor(): void
    {
        $this->report();
        $this->confirm();

        $log = TaskStatusLog::query()
            ->where('task_id', $this->task->getKey())
            ->where('to_status', TaskStatus::Active->value)
            ->sole();

        $this->assertSame(ActorType::Admin, $log->actor_type);
        $this->assertSame($this->admin->getKey(), (int) $log->actor_id);
    }

    public function test_confirming_writes_an_audit_row(): void
    {
        $this->report();
        $this->confirm();

        $row = AdminAuditLog::query()
            ->forAction(AdminAction::PaymentConfirmed, (int) $this->payment->getKey())
            ->sole();

        $this->assertSame($this->admin->getKey(), (int) $row->admin_id);
        $this->assertSame(AdminAction::PaymentConfirmed, $row->action);
        $this->assertSame('payment', $row->subject_type);
        $this->assertSame('10.0.0.7', $row->ip);
    }

    public function test_confirming_twice_is_rejected(): void
    {
        $this->report();
        $this->confirm();

        try {
            $this->confirm();
            $this->fail('konfirmasi kedua seharusnya ditolak');
        } catch (InvalidStatusTransitionException $e) {
            $this->assertSame(['from' => 'held', 'to' => 'held'], $e->context());
        }

        $this->assertSame(1, Activity::query()->where('task_id', $this->task->getKey())->count());
    }

    public function test_refunded_payment_cannot_be_reported(): void
    {
        $this->payment->forceFill([
            'status' => PaymentStatus::Refunded,
            'refunded_at' => now(),
        ])->save();

        $this->expectException(InvalidStatusTransitionException::class);

        $this->report();
    }

    // ── Penolakan pengelola ─────────────────────────────────────────────────

    public function test_rejecting_returns_the_payment_to_pending_with_a_reason(): void
    {
        $this->report();

        app(RejectPaymentAction::class)->handle(
            $this->payment->refresh(),
            $this->admin,
            new RejectPaymentData('Tidak ada mutasi masuk sejumlah itu.', '10.0.0.7'),
        );

        $payment = $this->payment->refresh();

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('Tidak ada mutasi masuk sejumlah itu.', $payment->rejection_reason);
        $this->assertNull($payment->held_at);
        $this->assertSame(0, Activity::query()->where('task_id', $this->task->getKey())->count());

        $this->assertSame(1, AdminAuditLog::query()
            ->forAction(AdminAction::PaymentRejected, (int) $payment->getKey())
            ->count());
    }

    /** Laporan baru menghapus alasan penolakan lama. */
    public function test_reporting_again_clears_the_previous_rejection_reason(): void
    {
        $this->report();
        app(RejectPaymentAction::class)->handle(
            $this->payment->refresh(),
            $this->admin,
            new RejectPaymentData('Nominalnya kurang.', null),
        );

        $this->report();

        $payment = $this->payment->refresh();

        $this->assertSame(PaymentStatus::AwaitingConfirmation, $payment->status);
        $this->assertNull($payment->rejection_reason);
    }

    /** Jejak keputusan tetap ada walau kolom di baris pembayaran tertimpa. */
    public function test_the_rejection_survives_in_the_audit_trail(): void
    {
        $this->report();
        app(RejectPaymentAction::class)->handle(
            $this->payment->refresh(),
            $this->admin,
            new RejectPaymentData('Nominalnya kurang.', null),
        );
        $this->report();
        $this->confirm();

        $row = AdminAuditLog::query()
            ->forAction(AdminAction::PaymentRejected, (int) $this->payment->getKey())
            ->sole();

        $this->assertSame('Nominalnya kurang.', $row->reason);
    }
}
