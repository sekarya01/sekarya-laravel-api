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
            'email' => ['required_without:username', 'nullable', 'email', 'max:180'],
            'username' => ['required_without:email', 'nullable', 'string', 'max:30'],
            'password' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required_without' => 'Masukkan email atau username.',
            'username.required_without' => 'Masukkan email atau username.',
        ];
    }
}
