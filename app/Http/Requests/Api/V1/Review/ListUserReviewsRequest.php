<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Review;

use App\Enums\ReviewerRole;
use App\Http\Requests\Api\V1\Concerns\PaginatesWithCursor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListUserReviewsRequest extends FormRequest
{
    use PaginatesWithCursor;

    /** @return array<string, mixed> */
    protected function filters(): array
    {
        // Reputasi dipisah per peran: sebagai pemberi kerja vs penerima kerja.
        return [
            'role' => ['sometimes', Rule::enum(ReviewerRole::class)],
        ];
    }

    public function role(): ?ReviewerRole
    {
        return $this->filled('role')
            ? ReviewerRole::from($this->string('role')->value())
            : null;
    }
}
