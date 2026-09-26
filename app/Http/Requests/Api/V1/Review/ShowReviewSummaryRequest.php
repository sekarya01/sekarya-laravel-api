<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Review;

use App\Enums\ReviewerRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ShowReviewSummaryRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Sama dengan `GET users/{user}/reviews?role=` — ringkasan dan
            // daftar di bawahnya harus menghitung himpunan yang sama.
            'role' => ['sometimes', Rule::enum(ReviewerRole::class)],
        ];
    }
}
