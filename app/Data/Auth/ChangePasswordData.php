<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Http\Requests\Api\V1\Auth\ChangePasswordRequest;

/**
 * Payload ganti kata sandi (G4) — kata sandi lama + yang baru.
 */
final readonly class ChangePasswordData
{
    public function __construct(
        public string $currentPassword,
        public string $newPassword,
    ) {}

    public static function fromRequest(ChangePasswordRequest $request): self
    {
        return new self(
            currentPassword: $request->string('current_password')->value(),
            newPassword: $request->string('password')->value(),
        );
    }
}
