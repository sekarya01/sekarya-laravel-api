<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Data\Wallet\CreateWithdrawalData;
use App\Enums\WalletEntryType;
use App\Enums\WalletWithdrawalStatus;
use App\Exceptions\Domain\BankAccountNotVerifiedException;
use App\Exceptions\Domain\TooManyPendingWalletRequestsException;
use App\Models\User;
use App\Models\WalletWithdrawal;
use App\Support\WalletLedger;
use Illuminate\Database\ConnectionInterface;

/**
 * Pekerja menarik saldonya ke rekening yang sudah diverifikasi.
 *
 * SALDONYA LANGSUNG BERKURANG, di sini, bukan saat pengelola mencairkan.
 * Kalau pemotongan menunggu pencairan, saldo yang sama bisa diminta
 * berkali-kali selama antrean pengelola belum tersentuh — tiga permintaan
 * sepuluh juta atas saldo sepuluh juta akan lolos semuanya, dan ketiganya
 * terlihat sah saat dibuka satu per satu.
 *
 * Konsekuensinya: penolakan dan pembatalan WAJIB mengembalikan tahanan itu.
 * Lihat `RejectWithdrawalAction` dan `CancelWithdrawalAction`.
 */
final class RequestWithdrawalAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly WalletLedger $ledger,
    ) {}

    public function handle(User $user, CreateWithdrawalData $data): WalletWithdrawal
    {
        return $this->db->transaction(function () use ($user, $data): WalletWithdrawal {
            // Rekening tujuan diambil dari verifikasi yang sudah disetujui,
            // tidak dari payload. Tanpa ini, saldo hasil kerja bisa dialirkan
            // ke rekening mana pun yang belum pernah dicocokkan dengan
            // identitas pemiliknya.
            $account = $user->verifiedBankAccount();

            if ($account === null) {
                throw new BankAccountNotVerifiedException;
            }

            $pending = WalletWithdrawal::query()
                ->where('user_id', $user->getKey())
                ->where('status', WalletWithdrawalStatus::Requested)
                ->lockForUpdate()
                ->count();

            $max = (int) config('sekarya.wallet.max_pending_requests');

            if ($pending >= $max) {
                throw TooManyPendingWalletRequestsException::withdrawals($pending, $max);
            }

            $withdrawal = new WalletWithdrawal;
            $withdrawal->fill([
                'user_id' => $user->getKey(),
                'amount' => $data->amount,
                'verification_id' => $account->getKey(),
            ]);
            $withdrawal->forceFill(['status' => WalletWithdrawalStatus::Requested])->save();

            // Ditahan SESUDAH barisnya ada, supaya baris buku besar bisa
            // menunjuk permintaannya. Saldo kurang melempar
            // `insufficient_balance` dari dalam kunci baris dompet, dan
            // transaksi ini membatalkan barisnya bersama-sama.
            $this->ledger->debit(
                $this->ledger->walletFor($user),
                WalletEntryType::Withdrawal,
                $data->amount,
                $withdrawal,
                'Penarikan saldo diminta',
            );

            return $withdrawal;
        });
    }
}
