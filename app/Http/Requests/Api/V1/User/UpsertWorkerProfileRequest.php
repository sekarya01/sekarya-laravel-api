<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\User;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Isi/ubah profil pekerja sendiri.
 *
 * Yang TIDAK ada di sini, dan tidak boleh ditambahkan:
 *
 * - **`gender` dan `birth_date`.** Keduanya identitas orangnya, sumbernya
 *   `users`, dan diubah lewat `PATCH /me`. Menerimanya di sini berarti satu
 *   orang bisa punya dua tanggal lahir yang berbeda dan keduanya "benar".
 * - **Agregat reputasi.** `worker_rating_avg`, `tasks_completed`, `bids_won`
 *   adalah angka yang dipakai pemberi kerja untuk memilih orang. Kalau bisa
 *   dikirim klien, ia bukan reputasi — ia kolom isian. Kolomnya juga tidak
 *   mass-assignable di modelnya, jadi ada dua lapis, bukan satu.
 */
final class UpsertWorkerProfileRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Semua NULLABLE, dan null di sini punya arti: "kembali ikut akun".
            // Itulah kenapa profil ini bisa dikosongkan sebagian tanpa
            // kehilangan datanya — nama dan nomor tetap ada di `users`.
            'display_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'contact_phone' => [
                'sometimes', 'nullable', 'string', 'max:20',
                'regex:/^\+?[0-9]{9,19}$/',
            ],
            'avatar_path' => ['sometimes', 'nullable', 'string', 'max:255'],

            'address_line' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:80'],
            'province' => ['sometimes', 'nullable', 'string', 'max:80'],
            'postal_code' => ['sometimes', 'nullable', 'string', 'max:10'],

            'latitude' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            // Sejauh apa ia bersedia berangkat. Batas atasnya menjaga radius
            // yang secara efektif berarti "seluruh Indonesia" — itu bukan
            // penyaring, dan feed jadi tidak berarti untuk yang memakainya.
            'radius_km' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'contact_phone.regex' => 'Nomor HP harus angka, boleh diawali +, panjang 9-19 digit.',
        ];
    }

    /**
     * Koordinat hanya berarti berpasangan.
     *
     * Satu lintang tanpa bujur bukan lokasi yang kurang lengkap — ia bukan
     * lokasi sama sekali, dan setiap kueri jarak akan melewatinya tanpa
     * memberi tahu siapa pun bahwa orang ini mengira dirinya sudah terpasang
     * di peta.
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
