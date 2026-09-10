<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Http\Requests\Api\V1\Admin\CreateAdminRequest;

/**
 * Akun pengelola baru. Perannya TIDAK ada di sini.
 *
 * Kalau peran datang dari payload, endpoint ini menjadi jalan membuat
 * super_admin kedua — dan satu-satunya yang menahannya cuma validasi.
 * Perannya dipaksa `admin` di CreateAdminAction, dan jumlah super_admin
 * dijamin indeks unique di basis data.
 */
final readonly class CreateAdminData
{
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $ip = null,
    ) {}

    public static function fromRequest(CreateAdminRequest $request): self
    {
        return new self(
            name: trim($request->string('name')->value()),
            email: mb_strtolower(trim($request->string('email')->value())),
            password: $request->string('password')->value(),
            ip: $request->ip(),
        );
    }
}
