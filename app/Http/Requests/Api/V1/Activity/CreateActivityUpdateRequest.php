<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Activity;

use Illuminate\Foundation\Http\FormRequest;

/** Catatan kemajuan pekerja pada sebuah activity (B9). */
final class CreateActivityUpdateRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'note' => ['required', 'string', 'max:200'],
            // Path foto dari `POST uploads purpose=update` — opsional.
            // Awalan folder divalidasi agar path task/bukti tidak bisa dipakai.
            'photo' => ['sometimes', 'nullable', 'string', 'max:255', 'starts_with:uploads/updates/'],
        ];
    }
}
