<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

final class ResendCodeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        // Sengaja TIDAK memakai exists:users,email — aturan itu akan
        // mengubah endpoint ini jadi alat pengecek email terdaftar.
        return [
            'email' => ['required', 'email', 'max:180'],
        ];
    }
}
