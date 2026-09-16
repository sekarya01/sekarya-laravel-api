<?php

declare(strict_types=1);

namespace App\Actions\Admin\Wallet;

use App\Enums\AdminAction;
use App\Enums\WalletWithdrawalStatus;
use App\Exceptions\Domain\WalletRequestNotPendingException;
use App\Models\Admin;
use App\Models\WalletWithdrawal;
use App\Support\AdminAuditRecorder;
use Illuminate\Database\ConnectionInterface;

/**
 * Pengelola sudah mentransfer ke rekening tujuan.
 *
 * TIDAK MENYENTUH SALDO, dan itu yang paling mudah salah dibaca di seluruh
 * alur ini. Saldonya sudah berkurang sejak penarikan diminta
 * (`RequestWithdrawalAction`); memotongnya lagi di sini berarti pengguna
 * membayar dua kali untuk satu pencairan. Yang terjadi di sini hanya
 * penandaan status dan pencatatan referensi transfer.
 */
final class CompleteWithdrawalAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function handle(
        WalletWithdrawal $withdrawal,
        Admin $admin,
        ?string $transferReference = null,
        ?string $ip = null,
    ): WalletWithdrawal {
        return $this->db->transaction(function () use ($withdrawal, $admin, $transferReference, $ip): WalletWithdrawal {
            $fresh = WalletWithdrawal::query()
                ->whereKey($withdrawal->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $fresh->status->canTransitionTo(WalletWithdrawalStatus::Completed)) {
                throw WalletRequestNotPendingException::withdrawal($fresh->status->value);
            }

            $now = now();

            $fresh->forceFill([
                'status' => WalletWithdrawalStatus::Completed,
                'processed_by' => $admin->getKey(),
                'processed_at' => $now,
                'completed_at' => $now,
                'transfer_reference' => $transferReference,
            ])->save();

            $this->audit->record(
                $admin,
                AdminAction::WalletWithdrawalCompleted,
                (int) $fresh->getKey(),
                sprintf(
                    'Rp%s%s',
                    number_format((float) $fresh->amount, 0, ',', '.'),
                    $transferReference === null ? '' : ', ref '.$transferReference,
                ),
                $ip,
            );

            return $fresh;
        });
    }
}
