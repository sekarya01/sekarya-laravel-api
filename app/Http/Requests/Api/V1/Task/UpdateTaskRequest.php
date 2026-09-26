<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Task;

use App\Models\Task;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Penyuntingan bersifat PARSIAL: ruas yang tidak dikirim tidak disentuh.
 *
 * Karena itu semua aturan memakai `sometimes`, dan dua perbandingan antar-ruas
 * (`budget_max` vs `budget_min`, `end_at` vs `needed_at`) tidak bisa memakai
 * `gte:`/`after:` biasa. Aturan itu membandingkan dengan ruas LAIN DI REQUEST
 * yang sama, dan ketika pembandingnya tidak ikut terkirim ia **lolos diam-diam**
 * — bukan menolak. Pada penyuntingan parsial itulah keadaan yang normal, jadi
 * `end_at` yang lebih awal dari `needed_at` TERSIMPAN selama `needed_at` tidak
 * ikut dikirim. Sudah diperiksa dengan menjalankan validatornya, bukan dibaca
 * dari dokumentasi.
 *
 * Pembandingnya harus nilai EFEKTIF: yang dikirim kalau ada, kalau tidak yang
 * tersimpan di task.
 */
final class UpdateTaskRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'integer', 'exists:categories,id'],
            'title' => ['sometimes', 'string', 'max:180'],
            'description' => ['sometimes', 'string', 'max:5000'],

            'budget_min' => ['sometimes', 'integer', 'min:1'],
            // Dibandingkan dengan budget_min EFEKTIF di after(); `gte:budget_min`
            // lolos begitu saja saat budget_min tidak ikut dikirim.
            'budget_max' => ['sometimes', 'nullable', 'integer', 'min:1'],

            'options' => ['sometimes', 'array', 'max:20'],
            'options.*.label' => ['required_with:options', 'string', 'max:80'],
            'options.*.value' => ['present'],
            'checklist' => ['sometimes', 'array', 'max:30'],
            'checklist.*' => ['string', 'max:120'],
            'photos' => ['sometimes', 'array', 'max:10'],
            'photos.*' => ['string', 'max:255'],

            'location_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'area' => ['sometimes', 'nullable', 'string', 'max:80'],
            'city' => ['sometimes', 'string', 'max:80'],
            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'is_remote' => ['sometimes', 'boolean'],

            'workers_needed' => ['sometimes', 'integer', 'min:1', 'max:500'],

            'needed_at' => ['sometimes', 'date', 'after:now'],
            // Dibandingkan dengan needed_at EFEKTIF di after(); `after:needed_at`
            // lolos begitu saja saat needed_at tidak ikut dikirim.
            'end_at' => ['sometimes', 'nullable', 'date'],
            'bidding_closes_at' => ['sometimes', 'nullable', 'date', 'after:now'],

            'skills' => ['sometimes', 'array', 'max:10'],
            'skills.*' => ['string', 'exists:skills,slug'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var Task $task */
            $task = $this->route('task');
            $errors = $validator->errors();

            if ($this->filled('budget_max') && ! $errors->hasAny(['budget_max', 'budget_min'])) {
                $min = $this->has('budget_min')
                    ? $this->integer('budget_min')
                    : (int) $task->budget_min;

                if ($this->integer('budget_max') < $min) {
                    $errors->add(
                        'budget_max',
                        'Budget maksimum tidak boleh lebih kecil dari budget minimum.',
                    );
                }
            }

            if ($this->filled('end_at') && ! $errors->hasAny(['end_at', 'needed_at'])) {
                $start = $this->has('needed_at')
                    ? Carbon::parse($this->string('needed_at')->value())
                    : $task->needed_at;

                if ($start !== null
                    && Carbon::parse($this->string('end_at')->value())->lessThanOrEqualTo($start)) {
                    $errors->add('end_at', 'Jadwal selesai harus setelah jadwal mulai.');
                }
            }
        });
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'workers_needed.min' => 'Jumlah pekerja minimal 1 orang.',
            'workers_needed.max' => 'Jumlah pekerja maksimal 500 orang untuk satu pekerjaan.',
        ];
    }
}
