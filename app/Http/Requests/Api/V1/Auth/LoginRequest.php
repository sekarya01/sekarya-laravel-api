<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Masuk: tepat satu dari email, username, atau nomor HP (U1).
 *
 * Nomor HP diterima dalam ejaan apa pun (`0812…`, `+62 812…`) — normalisasi
 * dan pencocokan ejaannya di Action, karena kolom `users.phone` bisa berisi
 * salah satu bentuk.
 */
final class LoginRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Tepat satu identitas. `required_without_all` mengurus "minimal
            // satu", `prohibits` mengurus "tidak lebih dari satu".
            'email' => [
                'required_without_all:username,phone', 'nullable', 'prohibits:username,phone',
                'email', 'max:180',
            ],
            'username' => [
                'required_without_all:email,phone', 'nullable', 'prohibits:email,phone',
                'string', 'max:30',
            ],
            'phone' => [
                'required_without_all:email,username', 'nullable', 'prohibits:email,username',
                'string', 'max:20', 'regex:'.PhoneNumber::PATTERN,
            ],
            'password' => ['required', 'string'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required_without_all' => 'Masukkan email, username, atau nomor HP.',
            'username.required_without_all' => 'Masukkan email, username, atau nomor HP.',
            'phone.required_without_all' => 'Masukkan email, username, atau nomor HP.',
        ];
    }
}
