<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Activity;

use Illuminate\Foundation\Http\FormRequest;

/** Lokasi langsung pekerja yang sedang menuju lokasi (B8). */
final class UpdateActivityLocationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Berpasangan dan wajib: satu titik butuh dua angka.
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
