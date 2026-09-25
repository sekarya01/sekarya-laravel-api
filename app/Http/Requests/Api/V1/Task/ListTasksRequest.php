<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Task;

use App\Data\Task\ListTasksData;
use App\Enums\TaskStatus;
use App\Http\Requests\Api\V1\Concerns\PaginatesWithCursor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListTasksRequest extends FormRequest
{
    use PaginatesWithCursor;

    /** @return array<string, mixed> */
    protected function filters(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(TaskStatus::class)],
            // Beberapa status sekaligus (U10) — satu tab Tugas = satu grup
            // status, dan grupnya harus disaring SERVER supaya halaman
            // cursor tidak pernah kosong. Hanya berlaku di `tasks/posted` dan
            // `tasks/worked`; feed selalu `open`. Tidak boleh bersama `status`:
            // dua penyaring yang bisa saling bertentangan hanya menghasilkan
            // halaman kosong tanpa keterangan.
            'statuses' => ['sometimes', 'array', 'min:1', 'max:'.count(TaskStatus::cases()), 'prohibits:status'],
            'statuses.*' => ['distinct', Rule::enum(TaskStatus::class)],
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            // Beberapa kategori sekaligus (U9). Batas 20 menjaga daftar IN
            // tetap berupa beberapa rentang pada indeks
            // (category_id, status, created_at), bukan pemindaian terselubung.
            // `category_id` lama tetap diterima, tapi tidak bersamaan.
            'category_ids' => ['sometimes', 'array', 'min:1', 'max:'.ListTasksData::MAX_CATEGORY_IDS, 'prohibits:category_id'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'city' => ['sometimes', 'string', 'max:80'],
            'budget_from' => ['sometimes', 'integer', 'min:0'],
            'budget_to' => ['sometimes', 'integer', 'gte:budget_from'],
            // Sembunyikan yang sudah saya tawar — default tetap tampil agar
            // pencari kerja bisa melihat & mengubah tawarannya.
            'exclude_my_bids' => ['sometimes', 'boolean'],

            // Waktu: seberapa baru task-nya diposting. Jam, bukan tanggal —
            // pencari kerja yang memantau feed berpikir dalam "sejak tadi
            // pagi", bukan dalam rentang kalender. Batas 720 jam (30 hari)
            // supaya nilainya tetap bertumpu pada indeks (status, created_at)
            // dan tidak berubah jadi "semua task" yang menyamar sebagai filter.
            'posted_within_hours' => ['sometimes', 'integer', 'min:1', 'max:720'],

            // Kata kunci. Dibatasi panjangnya karena tiap kata jadi satu term FTS.
            'q' => ['sometimes', 'string', 'min:2', 'max:120'],

            // Jarak: lat & lng wajib berpasangan, kalau tidak radius tak bermakna.
            //
            // TANPA 'sometimes' pada aturan required_with — 'sometimes' membuat
            // seluruh aturan dilewati ketika field tidak ada di input, sehingga
            // required_with tidak pernah dievaluasi dan "lat tanpa lng" lolos
            // lalu filternya diabaikan diam-diam.
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            // Punya default di DTO, jadi tidak diwajibkan.
            'radius_km' => ['sometimes', 'numeric', 'min:0.1', 'max:'.ListTasksData::MAX_RADIUS_KM],

            // Keahlian: daftar slug, atau ikuti skill milik sendiri.
            'skills' => ['sometimes'],
            'skills.*' => ['string', 'max:60'],
            'match_my_skills' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'lat.required_with' => 'Filter jarak butuh lat dan lng sekaligus.',
            'lng.required_with' => 'Filter jarak butuh lat dan lng sekaligus.',
        ];
    }
}
