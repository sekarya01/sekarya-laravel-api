<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use Illuminate\Foundation\Http\FormRequest;

final class StoreChatAttachmentRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $limits = (array) config('sekarya.chat.limits');
        $largest = max(array_map(static fn (array $l): int => (int) $l['max_kb'], $limits));

        return [
            // Batas per jenis (foto 10 MB, video 50 MB, …) diperiksa Action
            // setelah jenisnya diketahui dari MIME; di sini batas terbesar.
            'file' => [
                'required', 'file', 'max:'.$largest,
                'extensions:'.implode(',', (array) config('sekarya.chat.extensions')),
            ],
            // Durasi & dimensi video/audio dari perangkat — server hosting
            // bersama tidak punya ffprobe. Dimensi foto dibaca server sendiri.
            'duration' => ['sometimes', 'integer', 'min:0', 'max:86400'],
            'width' => ['sometimes', 'integer', 'min:0', 'max:20000'],
            'height' => ['sometimes', 'integer', 'min:0', 'max:20000'],
            'waveform' => ['sometimes', 'array', 'max:100'],
            'waveform.*' => ['integer', 'min:0', 'max:15'],
            // Bingkai awal video yang diambil perangkat (server tanpa ffmpeg).
            // Diabaikan untuk jenis selain video.
            'thumbnail' => ['sometimes', 'file', 'max:1024', 'mimes:jpg,jpeg,png,webp'],
        ];
    }
}
