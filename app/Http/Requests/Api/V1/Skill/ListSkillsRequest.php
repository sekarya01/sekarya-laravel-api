<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Skill;

use Illuminate\Foundation\Http\FormRequest;

final class ListSkillsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
        ];
    }
}
