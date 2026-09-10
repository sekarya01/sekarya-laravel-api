<?php

declare(strict_types=1);

namespace Tests\Feature\Web\SuperAdmin;

use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Models\Payment;
use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Antrean konfirmasi transfer lewat web: lapor → konfirmasi/tolak.
 */
final class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private function reportedPayment(): Payment
    {
        $poster = $this->activeUser();
        $worker = $this->activeUser();
        $task = Task::factory()->open()->create([
            'poster_id' => $poster->getKey(),
            'workers_needed' => 1,
        ]);
        $this->hireWorker($task, $worker, 150_000);
        $task->forceFill(['status' => TaskStatus::Dealt])->save();

        return Payment::factory()->create([
            'task_id' => $task->getKey(),
            'payer_id' => $poster->getKey(),
            'status' => PaymentStatus::AwaitingConfirmation,
            'amount' => 150_000,
            'reported_at' => now(),
        ]);
    }

    public function test_antrean_menampilkan_laporan(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $payment = $this->reportedPayment();

        $this->get(route('super_admin.payments.index'))
            ->assertOk()
            ->assertSee('150.000', false);
    }

    public function test_detail_tagihan_tampil(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $payment = $this->reportedPayment();

        $this->get(route('super_admin.payments.show', $payment->ulid))
            ->assertOk()
            ->assertSee($payment->task->task_number, false);
    }

    public function test_mengonfirmasi_menahan_dana_dan_membuka_activity(): void
    {
        $admin = $this->superAdmin();
        $this->actingAs($admin, 'admin_web');
        $payment = $this->reportedPayment();

        $this->post(route('super_admin.payments.confirm', $payment->ulid))
            ->assertSessionHas('status');

        $this->assertSame(
            PaymentStatus::Held,
            $payment->refresh()->status,
        );
        $this->assertDatabaseHas('activities', [
            'task_id' => $payment->task_id,
        ]);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'payment.confirmed',
            'subject_id' => $payment->getKey(),
        ]);
    }

    public function test_mengonfirmasi_dua_kali_ditolak(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $payment = $this->reportedPayment();

        $this->post(route('super_admin.payments.confirm', $payment->ulid))
            ->assertSessionHas('status');

        $this->post(route('super_admin.payments.confirm', $payment->ulid))
            ->assertSessionHasErrors('action');
    }

    public function test_menolak_mengembalikan_ke_pending(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $payment = $this->reportedPayment();

        $this->post(route('super_admin.payments.reject', $payment->ulid), [
            'reason' => 'Tidak ada mutasi masuk sejumlah itu hari ini.',
        ])->assertSessionHas('status');

        $payment->refresh();

        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertNotNull($payment->rejection_reason);
        $this->assertDatabaseHas('admin_audit_logs', [
            'action' => 'payment.rejected',
            'subject_id' => $payment->getKey(),
        ]);
    }

    public function test_menolak_tagihan_yang_sudah_ditahan_ditolak(): void
    {
        $this->actingAs($this->superAdmin(), 'admin_web');
        $payment = $this->reportedPayment();

        $this->post(route('super_admin.payments.confirm', $payment->ulid))
            ->assertSessionHas('status');

        $this->post(route('super_admin.payments.reject', $payment->ulid), [
            'reason' => 'Tidak ada mutasi masuk sejumlah itu hari ini.',
        ])->assertSessionHasErrors('action');

        $this->assertSame(PaymentStatus::Held, $payment->refresh()->status);
    }
}
