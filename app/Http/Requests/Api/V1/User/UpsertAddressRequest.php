<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Simpan alamat tersimpan (B6). PUT = ganti utuh: field opsional yang tidak
 * dikirim menjadi kosong, bukan mempertahankan nilai lama — satu alamat
 * adalah satu kesatuan (lihat catatan `resolvedAddress()` di UserWorker).
 */
final class UpsertAddressRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Nama pendek ("Rumah", "Kantor").
            'label' => ['nullable', 'string', 'max:40'],
            // Detail: jalan, nomor, RT/RW, patokan.
            'address_line' => ['required', 'string', 'max:250'],
            'city' => ['required', 'string', 'max:80'],
            'province' => ['nullable', 'string', 'max:80'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }

    /**
     * Koordinat hanya berarti berpasangan — aturan yang sama dengan
     * `PUT me/worker`.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $hasLatitude = $this->input('latitude') !== null;
            $hasLongitude = $this->input('longitude') !== null;

            if ($hasLatitude !== $hasLongitude) {
                $validator->errors()->add(
                    $hasLatitude ? 'longitude' : 'latitude',
                    'Lintang dan bujur harus diisi berpasangan.',
                );
            }
        });
    }
}
