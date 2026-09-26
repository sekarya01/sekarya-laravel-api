<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\City;

use Illuminate\Foundation\Http\FormRequest;

/** Pemilih kota (B12). */
final class ListCitiesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:80'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
