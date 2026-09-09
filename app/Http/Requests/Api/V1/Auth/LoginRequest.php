<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class LoginRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Salah satu wajib ada, tidak boleh dua-duanya kosong.
            'email' => ['required_without:phone', 'nullable', 'email', 'max:180'],
            'phone' => ['required_without:email', 'nullable', 'string', 'max:20'],
            'password' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required_without' => 'Masukkan email atau nomor HP.',
            'phone.required_without' => 'Masukkan email atau nomor HP.',
        ];
    }
}
