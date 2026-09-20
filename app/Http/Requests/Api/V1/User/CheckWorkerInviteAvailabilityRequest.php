<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Foundation\Http\FormRequest;

/**
 * "Apakah ada kode pendaftaran mitra yang hidup di wilayah saya?"
 *
 * city + province wajib — kunci jawabannya. TANGGAL TIDAK diminta dari
 * klien: "saat ini" dibaca dari jam server, karena jam perangkat bisa
 * dimundurkan untuk mengintip kode yang sebenarnya sudah mati.
 */
final class CheckWorkerInviteAvailabilityRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'city' => ['required', 'string', 'max:80'],
            'province' => ['required', 'string', 'max:80'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'city.required' => 'Kota wajib diisi untuk memeriksa ketersediaan kode.',
            'province.required' => 'Provinsi wajib diisi untuk memeriksa ketersediaan kode.',
        ];
    }
}
