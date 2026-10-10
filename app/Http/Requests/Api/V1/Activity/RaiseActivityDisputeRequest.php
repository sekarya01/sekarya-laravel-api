<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Activity;

use App\Enums\DisputeCategory;
use App\Support\ProofPhotos;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Pemberi kerja menyengketakan hasil satu mitra. Deskripsi wajib, foto opsional. */
final class RaiseActivityDisputeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category' => ['required', Rule::enum(DisputeCategory::class)],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
            'evidence_photos' => ['sometimes', 'array', 'max:5'],
            'evidence_photos.*' => ['string', 'max:255', 'distinct', app(ProofPhotos::class)->ownedRule($this->user())],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Jelaskan masalahnya supaya pengelola bisa menilai.',
            'reason.min' => 'Jelaskan masalahnya minimal :min karakter.',
        ];
    }
}
