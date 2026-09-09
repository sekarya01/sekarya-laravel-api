<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Enums\UserActiveMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:120'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'skills' => ['sometimes', 'array', 'max:20'],
            'skills.*' => ['string', 'exists:skills,slug'],
            // Avatar itu foto PUBLIK. Foto verifikasi punya endpoint sendiri.
            'avatar_path' => ['sometimes', 'nullable', 'string', 'max:255'],
            'address_line' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'province' => ['sometimes', 'nullable', 'string', 'max:80'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:10'],
            'active_mode' => ['sometimes', Rule::enum(UserActiveMode::class)],
            'theme' => ['sometimes', 'in:light,dark,system'],
        ];
    }
}
