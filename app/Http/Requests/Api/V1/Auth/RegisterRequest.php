<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

final class RegisterRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Kanonik baru: `first_name`. `name` tetap diterima sebagai alias
            // warisan agar klien lama tidak putus — dipecah jadi depan/belakang
            // di RegisterData::fromRequest().
            'first_name' => ['required_without:name', 'nullable', 'string', 'max:60'],
            'name' => ['required_without:first_name', 'nullable', 'string', 'max:120'],
            'last_name' => ['nullable', 'string', 'max:60'],
            'username' => [
                'nullable', 'string', 'max:30', 'regex:/\A[A-Za-z0-9._]+\z/',
                'unique:users,username',
            ],
            'email' => [
                'required',
                // Cek DNS dapat dimatikan per lingkungan — lihat config/sekarya.php.
                config('sekarya.auth.validate_email_dns') ? 'email:rfc,dns' : 'email:rfc',
                'max:180',
                'unique:users,email',
            ],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^\+?[0-9]{9,19}$/', 'unique:users,phone'],
            'password' => [
                'required',
                'confirmed',
                // Aturan bawaan Laravel: panjang, campuran huruf/angka, dan
                // dicek terhadap basis data kebocoran kata sandi publik.
                Password::min(8)->letters()->numbers()->uncompromised(),
            ],
            'city' => ['nullable', 'string', 'max:80'],
            'province' => ['nullable', 'string', 'max:80'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Nomor HP harus angka, boleh diawali +, panjang 9-19 digit.',
            'username.regex' => 'Username hanya boleh huruf, angka, titik, dan garis bawah.',
            'password.uncompromised' => 'Kata sandi ini pernah muncul di kebocoran data. Pilih yang lain.',
        ];
    }
}
