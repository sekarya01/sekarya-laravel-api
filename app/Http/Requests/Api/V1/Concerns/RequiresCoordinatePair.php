<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Concerns;

use Illuminate\Validation\Validator;

/**
 * Koordinat hanya berarti berpasangan — SATU aturan untuk task, alamat
 * tersimpan, dan profil pekerja.
 *
 * Satu lintang tanpa bujur bukan lokasi yang kurang lengkap — ia bukan lokasi
 * sama sekali, dan setiap kueri jarak melewatinya tanpa memberi tahu siapa pun
 * bahwa pemiliknya mengira dirinya sudah terpasang di peta.
 */
trait RequiresCoordinatePair
{
    protected function checkCoordinatePair(Validator $validator, mixed $latitude, mixed $longitude): void
    {
        $hasLatitude = $latitude !== null;
        $hasLongitude = $longitude !== null;

        if ($hasLatitude !== $hasLongitude) {
            $validator->errors()->add(
                $hasLatitude ? 'longitude' : 'latitude',
                'Lintang dan bujur harus diisi berpasangan.',
            );
        }
    }
}
