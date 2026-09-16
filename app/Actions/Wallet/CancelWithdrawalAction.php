<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Enums\WalletEntryType;
use App\Enums\WalletWithdrawalStatus;
use App\Exceptions\Domain\WalletRequestNotPendingException;
use App\Models\WalletWithdrawal;
use App\Support\WalletLedger;
use Illuminate\Database\ConnectionInterface;

/**
 * Pengguna menarik kembali permintaan penarikan yang belum dicairkan.
 *
 * Ini BUKAN kebalikan dari `CancelTopupAction`. Topup yang dibatalkan tidak
 * menyentuh saldo karena ia memang belum pernah menambah apa pun; penarikan
 * yang dibatalkan HARUS mengembalikan tahanannya, karena saldonya sudah
 * berkurang sejak diminta. Melewatkannya berarti uang pengguna hilang tanpa
 * pernah keluar ke rekening mana pun.
 */
final class CancelWithdrawalAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly WalletLedger $ledger,
    ) {}

    /**
     * Tanpa parameter aktor, seperti `CancelTopupAction`.
     *
     * Kepemilikan sudah menjadi gerbang rute (`->can('cancel', 'withdrawal')`,
     * `WalletWithdrawalPolicy`), dan tujuan pengembaliannya dibaca dari baris
     * penarikannya sendiri. Aktor yang dioper ke sini hanya akan jadi sumber
     * kedua untuk pertanyaan "dompet siapa" — dan yang kedua itu bisa salah.
     */
    public function handle(WalletWithdrawal $withdrawal): WalletWithdrawal
    {
        return $this->db->transaction(function () use ($withdrawal): WalletWithdrawal {
            $fresh = WalletWithdrawal::query()
                ->whereKey($withdrawal->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $fresh->status->canTransitionTo(WalletWithdrawalStatus::Cancelled)) {
                throw WalletRequestNotPendingException::withdrawal($fresh->status->value);
            }

            $fresh->forceFill([
                'status' => WalletWithdrawalStatus::Cancelled,
                'cancelled_at' => now(),
            ])->save();

            // Dikembalikan ke PEMILIK BARISNYA, bukan ke aktor yang dioper.
            // Kepemilikan sudah dijamin WalletWithdrawalPolicy, jadi keduanya
            // orang yang sama hari ini — tapi membaca pemiliknya dari barisnya
            // berarti jalur tulis baru yang lupa memeriksa tidak bisa
            // memindahkan uang ke dompet orang lain.
            $this->ledger->credit(
                $this->ledger->walletFor($fresh->user),
                WalletEntryType::WithdrawalReversal,
                (int) $fresh->amount,
                $fresh,
                'Penarikan dibatalkan pengguna',
            );

            return $fresh;
        });
    }
}
