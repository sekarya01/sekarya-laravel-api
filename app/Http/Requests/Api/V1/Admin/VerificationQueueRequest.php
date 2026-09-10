<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Http\Requests\Api\V1\Concerns\PaginatesWithCursor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class VerificationQueueRequest extends FormRequest
{
    use PaginatesWithCursor;

    /** @return array<string, mixed> */
    protected function filters(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(VerificationStatus::class)],
            'type' => ['sometimes', Rule::enum(VerificationType::class)],
        ];
    }
}
