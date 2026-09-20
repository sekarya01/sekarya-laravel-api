<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form kode undangan mitra: 8 char persis. Bentuk kasar dijaga di sini agar
 * Action tidak perlu membedakan "salah ketik" dari "kode asing" — keduanya
 * 404/422 yang sama, tapi request tak berbentuk tidak menyentuh DB sama sekali.
 */
final class RedeemWorkerInviteCodeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'size:8'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'code.required' => 'Kode undangan wajib diisi.',
            'code.size' => 'Kode undangan terdiri dari 8 karakter.',
        ];
    }
}
