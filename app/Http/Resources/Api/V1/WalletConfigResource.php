<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Data\Wallet\TopupAccount;
use App\Data\Wallet\WalletConfig;
use Illuminate\Http\Request;

/**
 * Konfigurasi dompet (B1): rekening tujuan + limit. Semua bilangan bulat
 * rupiah, sama seperti seluruh uang di API ini.
 *
 * @property WalletConfig $resource
 */
final class WalletConfigResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $config = $this->resource;

        return [
            'topup_accounts' => array_map(
                static fn (TopupAccount $account): array => [
                    'bank_code' => $account->bankCode,
                    'bank_name' => $account->bankName,
                    'account_number' => $account->accountNumber,
                    'account_holder' => $account->accountHolder,
                ],
                $config->topupAccounts,
            ),
            'limits' => (object) $config->limits,
        ];
    }
}
