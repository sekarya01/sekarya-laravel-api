<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Activity;

use App\Support\ProofPhotos;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

final class SubmitActivityRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $proofs = app(ProofPhotos::class);
        $minimum = $proofs->minimum();

        return [
            'worker_note' => ['nullable', 'string', 'max:2000'],
            // Bukti berfoto — dasar penyelesaian sengketa. Wajib sejak U11;
            // jumlah minimumnya di config (`sekarya.activities.min_proof_photos`).
            'proof_photos' => $minimum > 0
                ? ['required', 'array', 'min:'.$minimum, 'max:10']
                : ['sometimes', 'array', 'max:10'],
            'proof_photos.*' => [
                'string', 'max:255', 'distinct',
                // Hanya path dari `POST uploads` dengan `purpose=proof`, yang
                // diunggah ORANG YANG SAMA. Lihat ProofPhotos.
                function (string $attribute, mixed $value, Closure $fail) use ($proofs): void {
                    if (! is_string($value) || ! $proofs->isOwnedBy($value, $this->user())) {
                        $fail('Foto bukti harus diunggah sendiri lewat POST /uploads dengan purpose=proof.');
                    }
                },
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'proof_photos.required' => 'Lampirkan foto bukti hasil pengerjaan.',
            'proof_photos.min' => 'Lampirkan minimal :min foto bukti hasil pengerjaan.',
        ];
    }
}
