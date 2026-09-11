<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Http\Requests\Api\V1\Auth\LoginRequest;

final readonly class LoginData
{
    public function __construct(
        public string $password,
        public ?string $email = null,
        public ?string $username = null,
    ) {}

    public static function fromRequest(LoginRequest $request): self
    {
        return new self(
            password: $request->string('password')->value(),
            email: $request->filled('email')
                ? mb_strtolower(trim($request->string('email')->value()))
                : null,
            username: $request->filled('username')
                ? mb_strtolower(trim($request->string('username')->value()))
                : null,
        );
    }
}
