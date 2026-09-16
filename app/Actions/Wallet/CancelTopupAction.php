<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Enums\WalletTopupStatus;
use App\Exceptions\Domain\WalletRequestNotPendingException;
use App\Models\WalletTopup;
use Illuminate\Database\ConnectionInterface;

/**
 * Pengguna menarik kembali permintaan isi saldo yang belum diputuskan.
 *
 * Tidak menyentuh saldo — permintaan yang belum dikonfirmasi memang belum
 * pernah menambah apa pun. Yang dibebaskan adalah kuota permintaan menunggu,
 * dan satu baris di antrean pengelola.
 */
final class CancelTopupAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(WalletTopup $topup): WalletTopup
    {
        return $this->db->transaction(function () use ($topup): WalletTopup {
            $fresh = WalletTopup::query()
                ->whereKey($topup->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $fresh->status->canTransitionTo(WalletTopupStatus::Cancelled)) {
                throw WalletRequestNotPendingException::topup($fresh->status->value);
            }

            $fresh->forceFill([
                'status' => WalletTopupStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();

            return $fresh;
        });
    }
}
