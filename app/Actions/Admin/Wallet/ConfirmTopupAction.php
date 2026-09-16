<?php

declare(strict_types=1);

namespace App\Actions\Admin\Wallet;

use App\Enums\AdminAction;
use App\Enums\WalletEntryType;
use App\Enums\WalletTopupStatus;
use App\Exceptions\Domain\WalletRequestNotPendingException;
use App\Models\Admin;
use App\Models\WalletTopup;
use App\Support\AdminAuditRecorder;
use App\Support\WalletLedger;
use Illuminate\Database\ConnectionInterface;

/**
 * Pengelola melihat dana masuk → SALDO BERTAMBAH.
 *
 * Satu-satunya jalan saldo bisa bertambah dari isi ulang. Sama seperti
 * `held` pada pembayaran task, jalan ini sengaja tidak dimiliki penggunanya
 * sendiri: pernyataan "saya sudah transfer" dan fakta "dananya ada" adalah dua
 * hal berbeda, dan hanya orang yang melihat mutasi rekening yang bisa
 * menyatakan yang kedua.
 *
 * Dua lapis menjaga saldo tidak berlipat kalau tombolnya tertekan dua kali:
 * `lockForUpdate()` di sini, dan unique (reference_type, reference_id, type)
 * di `wallet_entries` sebagai pengaman terakhir — kunci hanya berlaku di
 * dalam transaksi, dan jalur tulis lain belum tentu mengambilnya.
 */
final class ConfirmTopupAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly WalletLedger $ledger,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function handle(WalletTopup $topup, Admin $admin, ?string $ip = null): WalletTopup
    {
        return $this->db->transaction(function () use ($topup, $admin, $ip): WalletTopup {
            $fresh = WalletTopup::query()
                ->whereKey($topup->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $fresh->status->canTransitionTo(WalletTopupStatus::Confirmed)) {
                throw WalletRequestNotPendingException::topup($fresh->status->value);
            }

            $now = now();

            $fresh->forceFill([
                'status' => WalletTopupStatus::Confirmed,
                'reviewed_by' => $admin->getKey(),
                'reviewed_at' => $now,
                'confirmed_at' => $now,
                'rejection_reason' => null,
            ])->save();

            $this->ledger->credit(
                $this->ledger->walletFor($fresh->user),
                WalletEntryType::Topup,
                (int) $fresh->amount,
                $fresh,
                'Isi saldo dikonfirmasi pengelola',
            );

            // DI DALAM transaksi: jejak "pengelola ini mengonfirmasi topup itu"
            // yang tertinggal setelah transaksinya gagal adalah jejak yang
            // berbohong. Lihat AdminAuditRecorder.
            $this->audit->record(
                $admin,
                AdminAction::WalletTopupConfirmed,
                (int) $fresh->getKey(),
                'Rp'.number_format((float) $fresh->amount, 0, ',', '.'),
                $ip,
            );

            return $fresh;
        });
    }
}
