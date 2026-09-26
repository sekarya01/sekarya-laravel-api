<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\DisputeResolution;
use App\Enums\DisputeStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Saring antrean sengketa (G5). */
final class ListDisputesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::enum(DisputeStatus::class)],
            'resolution' => ['sometimes', 'nullable', Rule::enum(DisputeResolution::class)],
        ];
    }

    public function status(): ?DisputeStatus
    {
        return $this->filled('status') ? DisputeStatus::from($this->string('status')->value()) : null;
    }
}
