<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Review;

use App\Enums\ReviewerRole;
use App\Http\Requests\Api\V1\Concerns\PaginatesWithCursor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListUserReviewsRequest extends FormRequest
{
    use PaginatesWithCursor;

    /** @return array<string, mixed> */
    protected function filters(): array
    {
        return [
            // Reputasi dipisah per peran: sebagai pemberi kerja vs penerima kerja.
            'role' => ['sometimes', Rule::enum(ReviewerRole::class)],
            // Chip bintang: `rating=5` tepat lima; `rating_max=2` untuk
            // "1-2★". Tidak boleh dikirim bersamaan — dua penyaring yang bisa
            // saling meniadakan hanya menghasilkan daftar kosong yang
            // membingungkan.
            'rating' => ['sometimes', 'integer', 'between:1,5', 'prohibits:rating_max'],
            'rating_max' => ['sometimes', 'integer', 'between:1,5'],
            // Cari di komentar (FULLTEXT lewat `review_search`, bukan LIKE).
            'q' => ['sometimes', 'nullable', 'string', 'max:100'],
            // Chip "Dengan Foto" (B15). `1` = hanya yang berfoto,
            // `0` = hanya yang tanpa foto.
            'has_photos' => ['sometimes', 'boolean'],
        ];
    }
}
