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
            // (KTP/selfie) TIDAK lewat sini — ia punya jalur sendiri yang
            // tidak publik (`POST me/verifications/documents`). `proof` =
            // foto bukti hasil kerja (U11), disimpan di `uploads/proofs`
            // dengan tanda pemilik. `review`/`update` = foto ulasan (B15) dan
            // foto catatan kemajuan (B9) — keduanya publik, folder terpisah
            // agar path-nya bisa divalidasi di endpoint pemakainya.
            'purpose' => ['sometimes', 'string', 'in:task,avatar,proof,review,update'],
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
