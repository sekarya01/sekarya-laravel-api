<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Enums\ReportReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Laporan pengguna (G7). */
final class ReportUserRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(ReportReason::class)],
            'note' => ['sometimes', 'nullable', 'string', 'max:500'],
            'task_id' => ['sometimes', 'nullable', 'string', 'size:26', 'exists:tasks,ulid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Pilih alasan laporan.',
        ];
    }
}
