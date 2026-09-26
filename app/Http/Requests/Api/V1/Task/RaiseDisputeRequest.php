<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Task;

use Illuminate\Foundation\Http\FormRequest;

/** Ajukan kendala (G5). */
final class RaiseDisputeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:500'],
            'evidence_photos' => ['sometimes', 'array', 'max:5'],
            'evidence_photos.*' => ['string', 'max:255', 'distinct', 'starts_with:uploads/reviews/'],
        ];
    }
}
