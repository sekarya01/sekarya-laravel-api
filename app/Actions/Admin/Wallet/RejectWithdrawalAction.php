<?php

declare(strict_types=1);

namespace App\Actions\Admin\Wallet;

use App\Data\Admin\RejectWalletRequestData;
use App\Enums\AdminAction;
use App\Enums\WalletEntryType;
use App\Enums\WalletWithdrawalStatus;
use App\Exceptions\Domain\WalletRequestNotPendingException;
use App\Models\Admin;
use App\Models\WalletWithdrawal;
use App\Support\AdminAuditRecorder;
use App\Support\WalletLedger;
use Illuminate\Database\ConnectionInterface;

/**
 * Pencairan ditolak → TAHANANNYA DIKEMBALIKAN.
 *
 * Pengembalian itu bukan tambahan yang enak dimiliki, ia syarat agar
 * pemotongan di muka boleh ada sama sekali. Saldo pengguna berkurang sejak ia
 * meminta penarikan; tanpa baris pengembalian di sini, penolakan pengelola
 * menghapus uang orang tanpa uang itu pernah keluar ke rekening mana pun —
 * dan tidak ada galat apa pun yang menandainya.
 */
final class RejectWithdrawalAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly WalletLedger $ledger,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function handle(
        WalletWithdrawal $withdrawal,
        Admin $admin,
        RejectWalletRequestData $data,
    ): WalletWithdrawal {
        return $this->db->transaction(function () use ($withdrawal, $admin, $data): WalletWithdrawal {
            $fresh = WalletWithdrawal::query()
                ->whereKey($withdrawal->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $fresh->status->canTransitionTo(WalletWithdrawalStatus::Rejected)) {
                throw WalletRequestNotPendingException::withdrawal($fresh->status->value);
            }

            $now = now();

            $fresh->forceFill([
                'status' => WalletWithdrawalStatus::Rejected,
                'processed_by' => $admin->getKey(),
                'processed_at' => $now,
                'rejected_at' => $now,
                'rejection_reason' => $data->reason,
            ])->save();

            $this->ledger->credit(
                $this->ledger->walletFor($fresh->user),
                WalletEntryType::WithdrawalReversal,
                (int) $fresh->amount,
                $fresh,
                'Penarikan ditolak pengelola',
            );

            $this->audit->record(
                $admin,
                AdminAction::WalletWithdrawalRejected,
                (int) $fresh->getKey(),
                $data->reason,
                $data->ip,
            );

            return $fresh;
        });
    }
}
