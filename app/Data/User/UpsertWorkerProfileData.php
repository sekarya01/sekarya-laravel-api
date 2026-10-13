<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Http\Requests\Api\V1\User\UpsertWorkerProfileRequest;
use App\Support\PhoneNumber;

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
        public ?string $headline = null,
        public ?string $contactPhone = null,
        public ?string $avatarPath = null,
        public ?string $addressLine = null,
        public ?string $city = null,
        public ?string $province = null,
        public ?string $postalCode = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?int $radiusKm = null,
        public ?bool $isAvailable = null,
        public array $present = [],
    ) {}

    /** Field payload yang boleh dianggap "ada" — namanya sama dengan nama kolom. */
    private const array FIELDS = [
        'display_name',
        'headline',
        'contact_phone',
        'avatar_path',
        'address_line',
        'city',
        'province',
        'postal_code',
        'latitude',
        'longitude',
        'radius_km',
        'is_available',
    ];

    public static function fromRequest(UpsertWorkerProfileRequest $request): self
    {
        $str = fn (string $key): ?string => $request->filled($key)
            ? trim($request->string($key)->value())
            : null;

        return new self(
            displayName: $str('display_name'),
            headline: $str('headline'),
            // Aturan & normalisasi nomor yang SAMA dengan pendaftaran (PhoneNumber).
            contactPhone: PhoneNumber::normalize($request->input('contact_phone')),
            avatarPath: $str('avatar_path'),
            addressLine: $str('address_line'),
            city: $str('city'),
            province: $str('province'),
            postalCode: $str('postal_code'),
            latitude: $request->filled('latitude') ? (float) $request->input('latitude') : null,
            longitude: $request->filled('longitude') ? (float) $request->input('longitude') : null,
            radiusKm: $request->filled('radius_km') ? (int) $request->input('radius_km') : null,
            isAvailable: $request->has('is_available') ? $request->boolean('is_available') : null,
            present: array_values(array_intersect(
                self::FIELDS,
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
            'headline' => $this->headline,
            'contact_phone' => $this->contactPhone,
            'avatar_path' => $this->avatarPath,
            'address_line' => $this->addressLine,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postalCode,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'radius_km' => $this->radiusKm,
            'is_available' => $this->isAvailable,
        ];

        // Tidak nullable (lihat request): dikirim atau tidak sama sekali.
        if ($this->isAvailable === null) {
            unset($values['is_available']);
        }

        if ($this->present === []) {
            return array_filter($values, fn (mixed $v): bool => $v !== null);
        }

        return array_intersect_key($values, array_flip($this->present));
    }

    public function changesAvailability(): bool
    {
        return $this->isAvailable !== null;
    }
}
