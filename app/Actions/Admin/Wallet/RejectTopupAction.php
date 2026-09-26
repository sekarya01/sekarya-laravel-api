<?php

declare(strict_types=1);

namespace App\Actions\Admin\Wallet;

use App\Data\Admin\RejectWalletRequestData;
use App\Enums\AdminAction;
use App\Enums\WalletTopupStatus;
use App\Exceptions\Domain\WalletRequestNotPendingException;
use App\Models\Admin;
use App\Models\WalletTopup;
use App\Support\AdminAuditRecorder;
use App\Support\Push\PushDispatcher;
use App\Support\Push\PushMessages;
use Illuminate\Database\ConnectionInterface;

/**
 * Dana tidak ditemukan di mutasi → permintaan ditolak.
 *
 * Statusnya FINAL, berbeda dari penolakan pembayaran task yang mengembalikan
 * tagihan ke `pending` supaya bisa dilaporkan ulang. Di sana barisnya melekat
 * pada satu task dan tidak boleh berlipat; di sini pengajuan ulang cukup
 * membuat baris baru, dan riwayat penolakan tetap utuh sebagai sinyal — akun
 * yang berulang kali mengaku transfer tanpa transfer adalah pola yang tidak
 * boleh bisa dihapus dari dalam.
 */
final class RejectTopupAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly AdminAuditRecorder $audit,
        private readonly PushDispatcher $push,
    ) {}

    public function handle(WalletTopup $topup, Admin $admin, RejectWalletRequestData $data): WalletTopup
    {
        return $this->db->transaction(function () use ($topup, $admin, $data): WalletTopup {
            $fresh = WalletTopup::query()
                ->whereKey($topup->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $fresh->status->canTransitionTo(WalletTopupStatus::Rejected)) {
                throw WalletRequestNotPendingException::topup($fresh->status->value);
            }

            $now = now();

            $fresh->forceFill([
                'status' => WalletTopupStatus::Rejected,
                'reviewed_by' => $admin->getKey(),
                'reviewed_at' => $now,
                'rejected_at' => $now,
                'rejection_reason' => $data->reason,
            ])->save();

            $this->audit->record(
                $admin,
                AdminAction::WalletTopupRejected,
                (int) $fresh->getKey(),
                $data->reason,
                $data->ip,
            );

            // Permintaan ditolak → pengguna diberi tahu (G11).
            $this->push->send((int) $fresh->user_id, PushMessages::topupRejected($fresh));

            return $fresh;
        });
    }
}
