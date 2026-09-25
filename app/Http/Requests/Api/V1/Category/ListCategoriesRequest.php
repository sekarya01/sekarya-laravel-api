<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Category;

use Illuminate\Foundation\Http\FormRequest;

final class ListCategoriesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Acuan harga per kota (U17); opsional.
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
        ];
    }
}
