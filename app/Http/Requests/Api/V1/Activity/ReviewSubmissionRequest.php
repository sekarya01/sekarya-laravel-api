<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Activity;

use Illuminate\Foundation\Http\FormRequest;

/** Dipakai poster untuk menyetujui atau menolak hasil kerja. */
final class ReviewSubmissionRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'poster_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
