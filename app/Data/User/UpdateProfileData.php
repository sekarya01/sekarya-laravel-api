<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Enums\UserActiveMode;
use App\Http\Requests\Api\V1\User\UpdateProfileRequest;

final readonly class UpdateProfileData
{
    /** @param list<string>|null $skills */
    public function __construct(
        public ?string $name = null,
        public ?string $bio = null,
        public ?array $skills = null,
        public ?string $avatarPath = null,
        public ?string $addressLine = null,
        public ?string $city = null,
        public ?string $province = null,
        public ?string $postalCode = null,
        public ?UserActiveMode $activeMode = null,
        public ?string $theme = null,
    ) {}

    public static function fromRequest(UpdateProfileRequest $request): self
    {
        $str = fn (string $key): ?string => $request->has($key)
            ? ($request->filled($key) ? trim($request->string($key)->value()) : null)
            : null;

        return new self(
            name: $str('name'),
            bio: $str('bio'),
            skills: $request->has('skills')
                ? array_values(array_filter(array_map('trim', $request->array('skills'))))
                : null,
            avatarPath: $str('avatar_path'),
            addressLine: $str('address_line'),
            city: $str('city'),
            province: $str('province'),
            postalCode: $str('postal_code'),
            activeMode: $request->filled('active_mode')
                ? UserActiveMode::from($request->string('active_mode')->value())
                : null,
            theme: $str('theme'),
        );
    }

    /**
     * Hanya field yang benar-benar dikirim. Menghindari menimpa kolom lain
     * dengan null hanya karena tidak disertakan di payload.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return array_filter([
            'name' => $this->name,
            'bio' => $this->bio,
            'avatar_path' => $this->avatarPath,
            'address_line' => $this->addressLine,
            'city' => $this->city,
            'province' => $this->province,
            'postal_code' => $this->postalCode,
            'active_mode' => $this->activeMode,
            'theme' => $this->theme,
        ], fn (mixed $v): bool => $v !== null);
    }
}
