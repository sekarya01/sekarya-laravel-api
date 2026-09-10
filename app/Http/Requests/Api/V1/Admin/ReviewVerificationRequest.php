<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dipakai `reject` dan `revoke`. Keduanya menuntut alasan.
 *
 * `min:10` bukan formalitas: alasan yang dibaca ulang berbulan-bulan
 * kemudian oleh orang lain (atau oleh penggunanya, yang berhak tahu mengapa
 * pengajuannya ditolak) tidak berguna kalau isinya "no".
 */
final class ReviewVerificationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'reason.required' => 'Sebutkan alasannya — pengguna berhak tahu apa yang harus diperbaiki.',
            'reason.min' => 'Alasan terlalu pendek untuk bisa dipahami penerimanya.',
        ];
    }
}
