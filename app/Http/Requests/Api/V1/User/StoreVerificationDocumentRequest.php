<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Unggah satu dokumen identitas (KTP/selfie) — G2a.
 *
 * Terpisah dari `POST uploads` karena disknya berbeda: dokumen identitas
 * TIDAK boleh publik, jadi disimpan di disk privat dan path-nya hanya dibaca
 * lewat endpoint ber-auth admin.
 */
final class StoreVerificationDocumentRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
        ];
    }
}
