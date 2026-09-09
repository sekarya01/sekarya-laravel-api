<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Activity;

use Illuminate\Foundation\Http\FormRequest;

final class SubmitActivityRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'worker_note' => ['nullable', 'string', 'max:2000'],
            // Bukti berfoto — dasar penyelesaian sengketa nanti.
            'proof_photos' => ['sometimes', 'array', 'max:10'],
            'proof_photos.*' => ['string', 'max:255'],
        ];
    }
}
