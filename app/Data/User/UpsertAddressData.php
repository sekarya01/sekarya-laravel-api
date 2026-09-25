<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Http\Requests\Api\V1\User\UpsertAddressRequest;

final readonly class UpsertAddressData
{
    public function __construct(
        public string $addressLine,
        public string $city,
        public ?string $label = null,
        public ?string $province = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
    ) {}

    public static function fromRequest(UpsertAddressRequest $request): self
    {
        $str = fn (string $key): ?string => $request->filled($key)
            ? trim($request->string($key)->value())
            : null;

        return new self(
            addressLine: trim($request->string('address_line')->value()),
            city: trim($request->string('city')->value()),
            label: $str('label'),
            province: $str('province'),
            latitude: $request->filled('latitude') ? (float) $request->input('latitude') : null,
            longitude: $request->filled('longitude') ? (float) $request->input('longitude') : null,
        );
    }

    /** @return array<string, mixed> Seluruh kolom — PUT mengganti utuh. */
    public function toAttributes(): array
    {
        return [
            'label' => $this->label,
            'address_line' => $this->addressLine,
            'city' => $this->city,
            'province' => $this->province,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
        ];
    }
}
