<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\WalletEntryType;
use App\Models\Activity;
use App\Models\Payment;
use App\Models\Task;
use App\Models\TaskFundMovement;
use App\Models\User;
use App\Support\WalletLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Baris riwayat saldo menyebut task penyebabnya (U3).
 */
final class WalletEntryTaskReferenceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private WalletLedger $ledger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReference();

        $this->user = $this->activeUser();
        $this->ledger = app(WalletLedger::class);
    }

    private function task(string $title): Task
    {
        return Task::factory()->create([
            'poster_id' => $this->user->getKey(),
            'category_id' => $this->anyCategory()->getKey(),
            'title' => $title,
        ]);
    }

    private function payment(Task $task): Payment
    {
        return Payment::factory()->held()->create([
            'task_id' => $task->getKey(),
            'payer_id' => $this->user->getKey(),
        ]);
    }

    private function movement(Task $task, Payment $payment): TaskFundMovement
    {
        $movement = new TaskFundMovement;
        $movement->forceFill([
            'task_id' => $task->getKey(),
            'payment_id' => $payment->getKey(),
            'kind' => TaskFundMovement::KIND_HOLD,
            'amount' => 10_000,
            'created_at' => now(),
        ])->save();

        return $movement;
    }

    /** @return array<string, mixed> type => task */
    private function tasksByType(): array
    {
        $rows = $this->asUser($this->user)
            ->getJson(route('v1.me.wallet.entries.index'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['*' => ['id', 'type', 'task']]])
            ->json('data');

        return array_column($rows, 'task', 'type');
    }

    public function test_each_task_bound_entry_names_its_task_and_others_are_null(): void
    {
        $wallet = $this->ledger->walletFor($this->user);
        $this->ledger->credit($wallet, WalletEntryType::Topup, 500_000, null, 'Isi saldo');

        // task_hold / task_release → task_fund_movements
        $held = $this->task('Pindahan Lemari Lantai 2');
        $heldPayment = $this->payment($held);
        $this->ledger->debit($wallet, WalletEntryType::TaskHold, 100_000, $this->movement($held, $heldPayment), 'Dana ditahan');

        // refund → payments
        $refunded = $this->task('Cuci AC');
        $this->ledger->credit($wallet, WalletEntryType::Refund, 50_000, $this->payment($refunded), 'Refund');

        // earning → activities
        $worked = $this->task('Rakit Meja');
        $activity = Activity::factory()->create([
            'task_id' => $worked->getKey(),
            'worker_id' => $this->user->getKey(),
            'payment_id' => $this->payment($worked)->getKey(),
        ]);
        $this->ledger->credit($wallet, WalletEntryType::Earning, 75_000, $activity, 'Upah');

        $tasks = $this->tasksByType();

        $this->assertNull($tasks['topup']);
        $this->assertSame(
            [
                'id' => $held->ulid,
                'task_number' => $held->task_number,
                'title' => 'Pindahan Lemari Lantai 2',
                'category' => [
                    'slug' => $held->category->slug,
                    'name' => $held->category->name,
                ],
            ],
            $tasks['task_hold'],
        );
        $this->assertSame('Cuci AC', $tasks['refund']['title']);
        $this->assertSame($worked->ulid, $tasks['earning']['id']);
        // Hanya empat kunci — id internal task tidak keluar.
        $this->assertSame(['id', 'task_number', 'title', 'category'], array_keys($tasks['earning']));
    }

    /** Task yang dihapus lunak tetap disebut: riwayat uang tidak kehilangan keterangannya. */
    public function test_a_soft_deleted_task_is_still_named(): void
    {
        $task = $this->task('Task yang dihapus');
        $wallet = $this->ledger->walletFor($this->user);
        $this->ledger->credit($wallet, WalletEntryType::Refund, 50_000, $this->payment($task), 'Refund');
        $task->delete();

        $this->assertSame('Task yang dihapus', $this->tasksByType()['refund']['title']);
    }

    /** Jumlah kueri tetap per halaman, tidak tumbuh per baris. */
    public function test_resolving_tasks_does_not_run_one_query_per_entry(): void
    {
        $wallet = $this->ledger->walletFor($this->user);

        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->asUser($this->user)->getJson(route('v1.me.wallet.entries.index'))->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $task = $this->task('Satu');
        $this->ledger->credit($wallet, WalletEntryType::Refund, 1_000, $this->payment($task), 'r');
        $one = $count();

        foreach (range(1, 5) as $i) {
            $task = $this->task('Task '.$i);
            $this->ledger->credit($wallet, WalletEntryType::Refund, 1_000, $this->payment($task), 'r');
        }

        $this->assertSame($one, $count());
    }
}
