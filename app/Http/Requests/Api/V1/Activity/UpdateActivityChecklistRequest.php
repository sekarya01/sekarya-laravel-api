<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Activity;

use Illuminate\Foundation\Http\FormRequest;

/** Centang checklist pekerjaan (B10). */
final class UpdateActivityChecklistRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'state' => ['required', 'array', 'max:30'],
            'state.*' => ['boolean'],
        ];
    }
}
