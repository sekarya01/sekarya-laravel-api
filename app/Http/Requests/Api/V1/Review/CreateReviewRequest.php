<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Review;

use App\Enums\ReviewerRole;
use App\Enums\ReviewTag;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreateReviewRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:2000'],

            // Tag pujian (chip di dialog rating). Himpunannya per arah
            // penilaian — lihat ReviewTag::forRole(). Tag milik arah lain
            // ditolak di sini sebagai `errors.tags.N`, bukan diam-diam dibuang.
            'tags' => ['sometimes', 'nullable', 'array', 'max:'.ReviewTag::MAX_PER_REVIEW],
            'tags.*' => ['string', 'distinct', Rule::in(ReviewTag::valuesForRole($this->reviewerRole()))],

            // Pekerja yang dinilai, saat penilainya adalah pemberi kerja.
            // Boleh dikosongkan HANYA kalau task itu punya tepat satu pekerja;
            // di luar itu Action menolak, karena menebak sasaran berarti
            // menaruh rating pada orang yang salah dan rating tidak bisa
            // dicabut. Pekerja yang menilai tidak perlu mengisinya — sasarannya
            // selalu pemberi kerja.
            'worker_id' => ['nullable', 'string', 'size:26'],
        ];
    }

    /**
     * Arah penilaian, hanya untuk memilih himpunan tag yang sah.
     *
     * Aman disimpulkan dari "pemberi kerja atau bukan": middleware
     * `can:review,task` sudah berjalan sebelum request ini diresolusi, jadi
     * yang bukan pemberi kerja PASTI pekerja task ini. Penentu arah yang
     * sebenarnya tetap CreateReviewAction::roleOf(), yang juga menjaga tag.
     */
    private function reviewerRole(): ReviewerRole
    {
        $task = $this->route('task');

        return $task instanceof Task && $task->poster_id === $this->user()?->getKey()
            ? ReviewerRole::Poster
            : ReviewerRole::Worker;
    }
}
