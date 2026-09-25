<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Upload;

use Illuminate\Foundation\Http\FormRequest;

final class StoreUploadRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Satu berkas per panggilan: mobile mengunggah tiap foto lalu
            // memakai `path` yang dikembalikan di `photos[]` saat buat task.
            // GIF/BMP/SVG disengaja dikecualikan — SVG bisa membawa skrip,
            // dan task tidak butuh animasi.
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            // Hanya tujuan yang aman dipublikasikan. Dokumen identitas
            // (KTP/selfie) TIDAK boleh lewat sini — ia tidak boleh
            // bisa diakses publik. `proof` = foto bukti hasil kerja (U11),
            // disimpan di `uploads/proofs` dengan tanda pemilik.
            'purpose' => ['sometimes', 'string', 'in:task,avatar,proof'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'file.max' => 'Ukuran berkas maksimal 10 MB.',
            'purpose.in' => 'Tujuan unggahan tidak dikenal.',
        ];
    }
}
