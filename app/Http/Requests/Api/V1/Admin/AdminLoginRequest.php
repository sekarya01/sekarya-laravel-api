<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class AdminLoginRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // TIDAK ada `exists:admins,email` — aturan itu akan menjawab 422
            // untuk alamat yang tidak terdaftar dan 401 untuk yang terdaftar,
            // sehingga endpoint ini menjadi alat menemukan alamat pengelola.
            'email' => ['required', 'email:rfc', 'max:180'],
            'password' => ['required', 'string'],
        ];
    }
}
