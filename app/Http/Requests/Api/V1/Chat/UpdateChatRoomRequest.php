<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateChatRoomRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Bisukan notifikasi room ini untuk diri sendiri.
            'is_muted' => ['required', 'boolean'],
        ];
    }
}
