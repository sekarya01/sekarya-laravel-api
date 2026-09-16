<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Wallet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Hanya nominal. Rekening tujuan tidak diterima dari klien — lihat
 * `CreateWithdrawalData`.
 *
 * Batas atas di sini BUKAN saldo yang dimiliki: itu keadaan sistem, bukan
 * bentuk permintaan, dan memeriksanya di sini berarti memeriksanya pada angka
 * yang sudah basi sebelum Action sempat mengunci dompetnya. Saldo kurang
 * keluar sebagai `insufficient_balance` dari `WalletLedger`.
 */
final class CreateWithdrawalRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => [
                'required', 'integer',
                'min:'.(int) config('sekarya.wallet.min_withdrawal'),
                'max:'.(int) config('sekarya.wallet.max_withdrawal'),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.min' => 'Minimum penarikan Rp'.number_format(
                (float) config('sekarya.wallet.min_withdrawal'), 0, ',', '.'
            ).'.',
            'amount.max' => 'Maksimum penarikan per permintaan Rp'.number_format(
                (float) config('sekarya.wallet.max_withdrawal'), 0, ',', '.'
            ).'.',
        ];
    }
}
