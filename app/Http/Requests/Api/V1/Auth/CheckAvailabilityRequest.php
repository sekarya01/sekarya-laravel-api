<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Cek ketersediaan email/username/phone SEBELUM akun dibuat.
 *
 * Aturan unique-nya cermin RegisterRequest supaya hasil pra-cek sama
 * dengan hasil validasi register. Minimal satu field harus diisi.
 */
final class CheckAvailabilityRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => [
                'required_without_all:username,phone',
                'nullable',
                config('sekarya.auth.validate_email_dns') ? 'email:rfc,dns' : 'email:rfc',
                'max:180',
                'unique:users,email',
            ],
            'username' => [
                'required_without_all:email,phone',
                'nullable',
                'string',
                'max:30',
                'regex:/\A[A-Za-z0-9._]+\z/',
                'unique:users,username',
            ],
            'phone' => [
                'required_without_all:email,username',
                'nullable',
                'string',
                'max:20',
                'regex:/^\+?[0-9]{9,19}$/',
                'unique:users,phone',
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.unique' => 'Email sudah terdaftar. Masuk atau pakai email lain.',
            'username.unique' => 'Username sudah dipakai. Pilih username lain.',
            'phone.unique' => 'Nomor HP sudah terdaftar. Masuk atau pakai nomor lain.',
            'phone.regex' => 'Nomor HP harus angka, boleh diawali +, panjang 9-19 digit.',
            'username.regex' => 'Username hanya boleh huruf, angka, titik, dan garis bawah.',
        ];
    }
}
