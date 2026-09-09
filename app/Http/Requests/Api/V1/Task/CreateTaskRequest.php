<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Task;

use Illuminate\Foundation\Http\FormRequest;

final class CreateTaskRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'title' => ['required', 'string', 'max:180'],
            'description' => ['required', 'string', 'max:5000'],

            'budget_min' => ['required', 'integer', 'min:1'],
            // Opsional. Kalau diisi, tidak boleh di bawah min.
            'budget_max' => ['nullable', 'integer', 'gte:budget_min'],

            'options' => ['sometimes', 'array', 'max:20'],
            'options.*.label' => ['required_with:options', 'string', 'max:80'],
            'options.*.value' => ['present'],
            'photos' => ['sometimes', 'array', 'max:10'],
            'photos.*' => ['string', 'max:255'],

            'location_text' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:80'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_remote' => ['sometimes', 'boolean'],

            // Jumlah pekerja yang dibutuhkan, sekaligus kuota pelamar:
            // task 30 orang menerima paling banyak 30 lamaran.
            // Batas atas ada supaya satu task tidak bisa membuka ribuan slot
            // dan mengubah kuota lamaran menjadi tak berbatas.
            'workers_needed' => ['sometimes', 'integer', 'min:1', 'max:500'],

            'needed_at' => ['nullable', 'date', 'after:now'],
            'bidding_closes_at' => ['nullable', 'date', 'after:now'],

            // Keahlian yang dibutuhkan — slug, dicek keberadaannya.
            'skills' => ['sometimes', 'array', 'max:10'],
            'skills.*' => ['string', 'exists:skills,slug'],

            'publish_now' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'budget_max.gte' => 'Budget maksimum tidak boleh lebih kecil dari budget minimum.',
            'workers_needed.min' => 'Jumlah pekerja minimal 1 orang.',
            'workers_needed.max' => 'Jumlah pekerja maksimal 500 orang untuk satu pekerjaan.',
        ];
    }
}
