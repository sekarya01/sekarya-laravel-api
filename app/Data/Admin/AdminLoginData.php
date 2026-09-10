<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Http\Requests\Api\V1\Admin\AdminLoginRequest;

/**
 * Login pengelola. Hanya email — pengelola tidak masuk lewat nomor HP.
 */
final readonly class AdminLoginData
{
    public function __construct(
        public string $email,
        public string $password,
        public ?string $ip = null,
    ) {}

    public static function fromRequest(AdminLoginRequest $request): self
    {
        return new self(
            // Dinormalkan sama seperti saat akunnya dibuat, kalau tidak
            // "Admin@x.test" tidak akan pernah cocok dengan barisnya.
            email: mb_strtolower(trim($request->string('email')->value())),
            password: $request->string('password')->value(),
            ip: $request->ip(),
        );
    }
}
