<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class VerifyEmailRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $length = (int) config('sekarya.verification.code_length');

        return [
            'email' => ['required', 'email', 'max:180'],
            // Panjang dicek setelah non-digit dibuang di DTO; di sini cukup
            // batas longgar agar "123 456" tidak ditolak lebih dulu.
            'code' => ['required', 'string', 'min:'.$length, 'max:'.($length + 4)],
        ];
    }
}
