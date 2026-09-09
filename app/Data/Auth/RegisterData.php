<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Http\Requests\Api\V1\Auth\RegisterRequest;

final readonly class RegisterData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $phone,
        public string $password,
        public ?string $city = null,
        public ?string $province = null,
    ) {}

    public static function fromRequest(RegisterRequest $request): self
    {
        return new self(
            name: trim($request->string('name')->value()),
            // Email dinormalkan huruf kecil: unique di database peka huruf
            // tergantung collation, dan "A@x.com" vs "a@x.com" harus dianggap
            // satu orang.
            email: mb_strtolower(trim($request->string('email')->value())),
            phone: preg_replace('/\s+/', '', $request->string('phone')->value()) ?? '',
            password: $request->string('password')->value(),
            city: $request->filled('city') ? trim($request->string('city')->value()) : null,
            province: $request->filled('province') ? trim($request->string('province')->value()) : null,
        );
    }
}
