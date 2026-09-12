<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class ForgotPasswordRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Sengaja TANPA `exists:users,email`: keberadaan akun diperiksa di
        // Action supaya galatnya keluar sebagai `{message, code}` bisnis
        // (`email_not_registered`), bukan bentuk validasi.
        return [
            'email' => ['required', 'email', 'max:180'],
        ];
    }
}
