<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Enums\DisputeResolution;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Putuskan sengketa (G5). */
final class ResolveDisputeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'resolution' => ['required', Rule::enum(DisputeResolution::class)],
            // Wajib: dikirim ke pemberi kerja & mitra lewat notif, dan jadi
            // alasan di jejak audit (AdminAction::requiresReason).
            'note' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'resolution.required' => 'Pilih hasil: release (dana ke pekerja) atau refund (dana kembali).',
        ];
    }
}
