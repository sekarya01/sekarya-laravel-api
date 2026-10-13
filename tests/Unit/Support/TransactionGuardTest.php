<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Enums\WalletEntryType;
use App\Models\Activity;
use App\Models\Task;
use App\Models\Wallet;
use App\Support\TaskEscrow;
use App\Support\WalletLedger;
use App\Support\WorkerPayout;
use LogicException;
use Tests\TestCase;

/**
 * Jalur tulis uang `[#tx: caller]` menolak dipanggil di luar transaksi.
 *
 * SENGAJA tanpa RefreshDatabase: trait itu membungkus tiap test dalam
 * transaksi, sehingga penjaganya tidak akan pernah terpicu dan test ini lulus
 * kosong. Penjaga berjalan sebelum kueri apa pun, jadi model tak tersimpan cukup.
 */
final class TransactionGuardTest extends TestCase
{
    public function test_wallet_ledger_refuses_outside_transaction(): void
    {
        $this->expectException(LogicException::class);

        app(WalletLedger::class)->credit(new Wallet, WalletEntryType::AdjustmentCredit, 1);
    }

    public function test_worker_payout_refuses_outside_transaction(): void
    {
        $this->expectException(LogicException::class);

        app(WorkerPayout::class)->pay(new Activity, 'upah');
    }

    public function test_task_escrow_sync_refuses_outside_transaction(): void
    {
        $this->expectException(LogicException::class);

        app(TaskEscrow::class)->sync(new Task, 'uji');
    }

    public function test_task_escrow_start_refuses_outside_transaction(): void
    {
        $this->expectException(LogicException::class);

        app(TaskEscrow::class)->start(new Task, 'uji');
    }
}
