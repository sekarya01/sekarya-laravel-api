<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pengelola menyatakan transfer ke rekening sudah dikirim.
 *
 * `transfer_reference` opsional tapi diminta: ia satu-satunya tali antara
 * baris di aplikasi ini dan baris di mutasi bank. Tanpa itu, sengketa
 * "uangnya belum sampai" tidak punya apa pun untuk dicocokkan.
 */
final class CompleteWithdrawalRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'transfer_reference' => ['sometimes', 'nullable', 'string', 'max:100'],
        ];
    }
}
