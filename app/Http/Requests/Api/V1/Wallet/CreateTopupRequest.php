<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Wallet;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Batasnya dibaca dari `config/sekarya.php` → `wallet`, bukan ditulis sebagai
 * literal di sini. Dua tempat yang menuliskan angka yang sama sendiri-sendiri
 * akan melenceng, dan yang melenceng adalah nominal yang boleh masuk.
 */
final class CreateTopupRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // `integer`, bukan `numeric`: uang di aplikasi ini bilangan bulat
            // dalam satuan terkecil. `numeric` menerima 10000.5, dan pecahan
            // rupiah yang dibulatkan diam-diam adalah selisih yang tidak bisa
            // dijelaskan ke pemiliknya.
            'amount' => [
                'required', 'integer',
                'min:'.(int) config('sekarya.wallet.min_topup'),
                'max:'.(int) config('sekarya.wallet.max_topup'),
            ],
            'sender_note' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.min' => 'Minimum isi saldo Rp'.number_format(
                (float) config('sekarya.wallet.min_topup'), 0, ',', '.'
            ).'.',
            'amount.max' => 'Maksimum isi saldo per permintaan Rp'.number_format(
                (float) config('sekarya.wallet.max_topup'), 0, ',', '.'
            ).'.',
        ];
    }
}
