<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use App\Enums\Gender;
use App\Http\Requests\Api\V1\Concerns\PaginatesWithCursor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Daftar pekerja yang siap menerima pekerjaan.
 *
 * Penyaringnya sengaja sedikit, dan tiap satunya bertumpu indeks yang sudah
 * ada: `user_workers (city, province)` dan `users.gender`. TIDAK ada pencarian
 * nama sebagian — `LIKE '%budi%'` tidak bisa memakai indeks apa pun, dan
 * proyek ini nol kemunculan `LIKE` di `app/`. Pencarian nama yang benar butuh
 * tabel indeks terbalik dengan normalisasi di dua sisi, seperti `task_search`;
 * itu slice sendiri.
 */
final class ListWorkersRequest extends FormRequest
{
    use PaginatesWithCursor;

    /** @return array<string, mixed> */
    protected function filters(): array
    {
        return [
            // Kota kerja: yang dibandingkan alamat kerja kalau ia mengisinya,
            // kalau tidak domisili akunnya — resolusinya di Action.
            'city' => ['sometimes', 'string', 'max:80'],
            'province' => ['sometimes', 'string', 'max:80'],
            // Ada pekerjaan yang memang menuntutnya (jaga anak, perawat
            // lansia, pekerjaan di ruang tertutup). Nilainya hanya dua, sama
            // seperti kolomnya.
            'gender' => ['sometimes', Rule::enum(Gender::class)],
            // Penyaring, bukan gerbang. Tanpa parameter ini daftarnya memuat
            // semua pekerja — yang terverifikasi maupun belum.
            'ready_to_work' => ['sometimes', 'boolean'],
        ];
    }
}
