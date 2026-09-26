<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\ReportStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Saring antrean laporan admin (G7). */
final class ListReportsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'nullable', Rule::enum(ReportStatus::class)],
        ];
    }

    public function status(): ?ReportStatus
    {
        return $this->filled('status')
            ? ReportStatus::from($this->string('status')->value())
            : null;
    }
}
