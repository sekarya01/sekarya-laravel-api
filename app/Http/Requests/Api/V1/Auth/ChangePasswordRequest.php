<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Ganti kata sandi (G4) — hanya untuk pengguna yang sudah login.
 */
final class ChangePasswordRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => [
                'required',
                'confirmed',
                // Aturan yang sama dengan pendaftaran: panjang, campuran
                // huruf/angka, dan dicek terhadap basis data kebocoran.
                Password::min(8)->letters()->numbers()->uncompromised(),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'current_password.required' => 'Masukkan kata sandi saat ini.',
            'password.uncompromised' => 'Kata sandi ini pernah muncul di kebocoran data. Pilih yang lain.',
        ];
    }
}
