<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Review;

use Illuminate\Foundation\Http\FormRequest;

final class CreateReviewRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],

            // Pekerja yang dinilai, saat penilainya adalah pemberi kerja.
            // Boleh dikosongkan HANYA kalau task itu punya tepat satu pekerja;
            // di luar itu Action menolak, karena menebak sasaran berarti
            // menaruh rating pada orang yang salah dan rating tidak bisa
            // dicabut. Pekerja yang menilai tidak perlu mengisinya — sasarannya
            // selalu pemberi kerja.
            'worker_id' => ['nullable', 'string', 'size:26'],
        ];
    }
}
