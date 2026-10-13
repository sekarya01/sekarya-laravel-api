<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Actions\Admin\Payment\ConfirmPaymentAction;
use App\Actions\Payment\ReportTransferAction;
use App\Actions\Task\CancelTaskAction;
use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Pembatalan task dan konfirmasi transfer (jalur lama) menyentuh tagihan yang
 * sama. Keduanya WAJIB mengunci task lalu tagihan — urutan sama, supaya tidak
 * saling menunggu (deadlock) dan tidak menimpa tagihan dari salinan basi.
 *
 * Dua transaksi paralel tidak bisa dijalankan di PHPUnit, jadi yang diuji
 * adalah kontraknya: urutan kueri `for update`.
 */
final class PaymentLockOrderTest extends TestCase
{
    use RefreshDatabase;

    private User $poster;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();
        $this->poster = $this->activeUser();
        $this->task = Task::factory()->create([
            'poster_id' => $this->poster->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'status' => TaskStatus::Dealt,
        ]);
        $this->hireWorker($this->task, $this->activeUser(), 180_000);
        Payment::factory()->create([
            'task_id' => $this->task->getKey(),
            'payer_id' => $this->poster->getKey(),
            'amount' => 180_000,
        ]);
    }

    public function test_cancel_locks_the_task_then_the_payment(): void
    {
        $locked = $this->lockedTables(fn () => app(CancelTaskAction::class)->handle($this->task, $this->poster));

        $this->assertSame(['tasks', 'payments'], array_slice($locked, 0, 2));
        $this->assertSame(PaymentStatus::Cancelled, $this->task->payment()->firstOrFail()->status);
    }

    public function test_confirm_locks_the_task_then_the_payment(): void
    {
        app(ReportTransferAction::class)->handle($this->task, $this->poster);
        $payment = $this->task->payment()->firstOrFail();

        $locked = $this->lockedTables(fn () => app(ConfirmPaymentAction::class)->handle($payment, $this->activeAdmin()));

        $this->assertSame(['tasks', 'payments'], array_slice($locked, 0, 2));
        $this->assertSame(PaymentStatus::Held, $payment->refresh()->status);
    }

    /** @return list<string> tabel yang dikunci, berurutan */
    private function lockedTables(callable $call): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $call();
        $queries = array_column(DB::getQueryLog(), 'query');
        DB::disableQueryLog();

        $tables = [];
        foreach ($queries as $sql) {
            if (str_ends_with($sql, 'for update') && preg_match('/from `(\w+)`/', $sql, $m)) {
                $tables[] = $m[1];
            }
        }

        return $tables;
    }
}
