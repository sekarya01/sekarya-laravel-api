<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Admin;

use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Models\Admin;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Antrean konfirmasi transfer, lewat HTTP. */
final class AdminPaymentApiTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    private User $poster;

    private User $worker;

    private Task $task;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->admin = $this->activeAdmin();
        $this->poster = $this->activeUser();
        $this->worker = $this->activeUser();

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

    private function report(): void
    {
        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.payment.hold', $this->task))
            ->assertOk();
    }

    // ── Antrean ─────────────────────────────────────────────────────────────

    /** Bawaannya HANYA yang menunggu tindakan manusia. */
    public function test_the_queue_shows_only_reported_transfers(): void
    {
        $this->asAdmin($this->admin)->getJson(route('v1.admin.payments.index'))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->report();

        $this->asAdmin($this->admin)->getJson(route('v1.admin.payments.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'awaiting_confirmation')
            ->assertJsonPath('data.0.awaits_confirmation', true)
            ->assertJsonPath('data.0.amount', 220_000)
            ->assertJsonPath('data.0.task.workers_hired', 1)
            ->assertJsonPath('data.0.payer.id', $this->poster->ulid)
            ->assertJsonStructure([
                'data' => [['id', 'status', 'amount', 'is_held', 'awaits_confirmation',
                    'reported_at', 'rejection_reason', 'paid_at', 'held_at', 'released_at',
                    'task' => ['id', 'task_number', 'title', 'status', 'workers_hired', 'agreed_amount'],
                    'payer', 'created_at']],
            ]);
    }

    public function test_the_queue_never_carries_a_gateway_payload(): void
    {
        $this->report();

        $body = $this->asAdmin($this->admin)
            ->getJson(route('v1.admin.payments.index'))
            ->assertOk()
            ->content();

        $this->assertStringNotContainsString('gateway_payload', $body);
        $this->assertStringNotContainsString('npwp', $body);
    }

    // ── Konfirmasi ──────────────────────────────────────────────────────────

    public function test_confirming_holds_the_money_and_opens_the_work(): void
    {
        $this->report();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.payments.confirm', $this->payment))
            ->assertOk()
            ->assertJsonPath('data.status', 'held')
            ->assertJsonPath('data.is_held', true)
            ->assertJsonPath('data.task.status', 'active');

        // Pekerjaan pekerja baru terbuka SEKARANG.
        $this->asUser($this->worker)->getJson(route('v1.activities.mine'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'open')
            ->assertJsonPath('data.0.agreed_amount', 220_000);
    }

    public function test_confirming_a_payment_that_was_never_reported_is_refused(): void
    {
        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.payments.confirm', $this->payment))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_status_transition')
            ->assertJsonPath('context.from', 'pending');

        $this->asUser($this->worker)->getJson(route('v1.activities.mine'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_confirming_twice_is_refused(): void
    {
        $this->report();
        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.payments.confirm', $this->payment))->assertOk();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.payments.confirm', $this->payment))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'invalid_status_transition');
    }

    // ── Penolakan ───────────────────────────────────────────────────────────

    public function test_rejecting_returns_it_to_pending_and_tells_the_poster_why(): void
    {
        $this->report();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.payments.reject', $this->payment), [
                'reason' => 'Tidak ada mutasi masuk sejumlah itu hari ini.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.is_held', false);

        // Pemberi kerja membacanya di endpointnya sendiri.
        $this->asUser($this->poster)->getJson(route('v1.tasks.payment.show', $this->task))
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.rejection_reason', 'Tidak ada mutasi masuk sejumlah itu hari ini.');

        // Dan tidak ada pekerjaan yang terbuka.
        $this->asUser($this->worker)->getJson(route('v1.activities.mine'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_rejecting_requires_a_reason(): void
    {
        $this->report();

        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.payments.reject', $this->payment))
            ->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors' => ['reason']]);

        $this->assertSame(
            PaymentStatus::AwaitingConfirmation,
            $this->payment->refresh()->status,
        );
    }

    public function test_the_poster_can_report_again_after_a_rejection(): void
    {
        $this->report();
        $this->asAdmin($this->admin)
            ->postJson(route('v1.admin.payments.reject', $this->payment), [
                'reason' => 'Nominalnya kurang dari yang disepakati.',
            ])->assertOk();

        $this->asUser($this->poster)
            ->postJson(route('v1.tasks.payment.hold', $this->task))
            ->assertOk()
            ->assertJsonPath('data.awaits_confirmation', true)
            // Alasan lama tidak boleh tertinggal — pemberi kerja sudah
            // memperbaikinya.
            ->assertJsonPath('data.rejection_reason', null);
    }

    public function test_a_user_cannot_reach_the_payment_queue(): void
    {
        $this->report();

        $this->asUser($this->poster)->getJson(route('v1.admin.payments.index'))->assertUnauthorized();
        $this->asUser($this->poster)
            ->postJson(route('v1.admin.payments.reject', $this->payment), ['reason' => 'saya mau'])
            ->assertUnauthorized();
    }
}
