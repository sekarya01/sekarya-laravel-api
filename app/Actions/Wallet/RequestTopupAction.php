<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Data\Wallet\CreateTopupData;
use App\Enums\WalletTopupStatus;
use App\Exceptions\Domain\TooManyPendingWalletRequestsException;
use App\Models\User;
use App\Models\WalletTopup;
use Illuminate\Database\ConnectionInterface;

/**
 * Pengguna menyatakan sudah mentransfer sejumlah uang untuk mengisi saldo.
 *
 * TIDAK menambah saldo apa pun. Pola yang sama persis dengan laporan transfer
 * task: yang menyatakan uangnya benar-benar masuk adalah orang yang melihat
 * mutasi rekening, bukan yang mengaku sudah mengirim. Kalau panggilan ini
 * menambah saldo, siapa pun bisa mengisi dompetnya sendiri dengan satu
 * permintaan HTTP — dan saldo itu bisa langsung ditarik ke rekening.
 */
final class RequestTopupAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(User $user, CreateTopupData $data): WalletTopup
    {
        return $this->db->transaction(function () use ($user, $data): WalletTopup {
            $pending = WalletTopup::query()
                ->where('user_id', $user->getKey())
                ->where('status', WalletTopupStatus::AwaitingConfirmation)
                ->lockForUpdate()
                ->count();

            $max = (int) config('sekarya.wallet.max_pending_requests');

            if ($pending >= $max) {
                throw TooManyPendingWalletRequestsException::topups($pending, $max);
            }

            $topup = new WalletTopup;
            $topup->fill([
                'user_id' => $user->getKey(),
                'amount' => $data->amount,
                'sender_note' => $data->senderNote,
            ]);
            // `status` tidak mass-assignable — disetel di sini, eksplisit,
            // supaya nilainya tidak bergantung pada bawaan kolom yang bisa
            // berubah tanpa ada yang membaca ulang Action ini.
            $topup->forceFill(['status' => WalletTopupStatus::AwaitingConfirmation])->save();

            return $topup;
        });
    }
}
