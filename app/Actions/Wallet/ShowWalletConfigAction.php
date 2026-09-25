<?php

declare(strict_types=1);

namespace App\Actions\Wallet;

use App\Data\Wallet\TopupAccount;
use App\Data\Wallet\WalletConfig;

/**
 * Rekening tujuan + batas nominal untuk layar Isi Saldo/Tarik Saldo (B1).
 *
 * Tidak menyentuh basis data maupun pengguna: isinya murni konfigurasi.
 * Rekening tujuan diisi dari env (lihat `config/sekarya.php`), jadi mengganti
 * nomor rekening tidak menuntut deploy kode.
 */
final class ShowWalletConfigAction
{
    public function handle(): WalletConfig
    {
        /** @var list<array<string, mixed>> $accounts */
        $accounts = (array) config('sekarya.wallet.topup_accounts', []);

        return new WalletConfig(
            topupAccounts: array_map(
                static fn (array $row): TopupAccount => new TopupAccount(
                    bankCode: isset($row['bank_code']) ? (string) $row['bank_code'] : null,
                    bankName: isset($row['bank_name']) ? (string) $row['bank_name'] : null,
                    accountNumber: isset($row['account_number']) ? (string) $row['account_number'] : null,
                    accountHolder: isset($row['account_holder']) ? (string) $row['account_holder'] : null,
                ),
                $accounts,
            ),
            limits: [
                'min_topup' => (int) config('sekarya.wallet.min_topup'),
                'max_topup' => (int) config('sekarya.wallet.max_topup'),
                'min_withdrawal' => (int) config('sekarya.wallet.min_withdrawal'),
                'max_withdrawal' => (int) config('sekarya.wallet.max_withdrawal'),
                'max_pending_requests' => (int) config('sekarya.wallet.max_pending_requests'),
            ],
        );
    }
}
