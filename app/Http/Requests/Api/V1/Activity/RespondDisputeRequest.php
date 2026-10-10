<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Activity;

use App\Support\ProofPhotos;
use Illuminate\Foundation\Http\FormRequest;

/** Tanggapan mitra atas sengketa hasil kerjanya — satu kali. */
final class RespondDisputeRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'response' => ['required', 'string', 'min:10', 'max:500'],
            'evidence_photos' => ['sometimes', 'array', 'max:5'],
            'evidence_photos.*' => ['string', 'max:255', 'distinct', app(ProofPhotos::class)->ownedRule($this->user())],
        ];
    }
}
