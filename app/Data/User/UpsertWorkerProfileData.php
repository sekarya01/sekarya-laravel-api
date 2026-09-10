<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Http\Requests\Api\V1\User\UpsertWorkerProfileRequest;

/**
 * Isi profil pekerja yang dikirim klien.
 *
 * Sama seperti `UpdateProfileData`, yang dibawa bukan hanya nilainya tapi juga
 * DAFTAR FIELD YANG DIKIRIM — dan di sini bedanya lebih tajam lagi: null pada
 * profil ini berarti "kembali ikut akun", bukan "kosongkan". Tanpa daftar itu,
 * satu-satunya cara membedakan "tidak menyebut nama tampilan" dari "hapus nama
 * tampilan saya, pakai nama akun" adalah menebak — dan dua tebakan itu
 * menghasilkan profil yang berbeda.
 */
final readonly class UpsertWorkerProfileData
{
    /** @param list<string> $present Kunci yang benar-benar ada di payload. */
    public function __construct(
        public ?string $displayName = null,
        public ?string $contactPhone = null,
        public ?string $avatarPath = null,
        public ?string $addressLine = null,
        public ?string $city = null,
        public ?string $province = null,
        public ?string $postalCode = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?int $radiusKm = null,
        public array $present = [],
    ) {}

    /** Nama field di payload -> nama kolom. Satu tempat, dipakai dua arah. */
    private const array COLUMN_MAP = [
        'display_name' => 'display_name',
        'contact_phone' => 'contact_phone',
        'avatar_path' => 'avatar_path',
        'address_line' => 'address_line',
        'city' => 'city',
        'province' => 'province',
        'postal_code' => 'postal_code',
        'latitude' => 'latitude',
        'longitude' => 'longitude',
        'radius_km' => 'radius_km',
    ];

    public static function fromRequest(UpsertWorkerProfileRequest $request): self
    {
        $str = fn (string $key): ?string => $request->filled($key)
            ? trim($request->string($key)->value())
            : null;

        return new self(
            displayName: $str('display_name'),
            contactPhone: $request->filled('contact_phone')
                // Spasi dibuang seperti pada pendaftaran: "+62 812 ..." dan
                // "+62812..." adalah nomor yang sama, dan hanya satu bentuk
                // yang boleh masuk basis data.
                ? preg_replace('/\s+/', '', $request->string('contact_phone')->value())
                : null,
            avatarPath: $str('avatar_path'),
            addressLine: $str('address_line'),
            city: $str('city'),
            province: $str('province'),
            postalCode: $str('postal_code'),
            latitude: $request->filled('latitude') ? (float) $request->input('latitude') : null,
            longitude: $request->filled('longitude') ? (float) $request->input('longitude') : null,
            radiusKm: $request->filled('radius_km') ? (int) $request->input('radius_km') : null,
            present: array_values(array_intersect(
                array_keys(self::COLUMN_MAP),
                array_keys($request->all()),
            )),
        );
    }

    /**
     * Hanya field yang dikirim. Nilainya boleh null — dan null BERARTI
     * kembali mewarisi dari akun.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $values = [
            'display_name' => $this->displayName,
            'contact_phone' => $this->contactPhone,
            'avatar_path' => $this->avatarPath,
            'address_line' => $this->addressLine,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postalCode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius_km' => $this->radiusKm,
        ];

        if ($this->present === []) {
            return array_filter($values, fn (mixed $v): bool => $v !== null);
        }

        $columns = array_map(
            fn (string $field): string => self::COLUMN_MAP[$field],
            $this->present,
        );

        return array_intersect_key($values, array_flip($columns));
    }
}
