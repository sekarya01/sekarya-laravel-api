<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Http\Requests\Api\V1\Auth\RegisterRequest;

final readonly class RegisterData
{
    public function __construct(
        public string $firstName,
        public ?string $lastName,
        public ?string $username,
        public string $email,
        public ?string $phone,
        public string $password,
        public ?string $city = null,
        public ?string $province = null,
    ) {}

    public static function fromRequest(RegisterRequest $request): self
    {
        // Klien lama mengirim `name` ("Budi Prasetyo") tanpa `first_name`:
        // dipecah jadi depan/belakang dengan aturan yang sama seperti
        // backfill migrasi identitas.
        $first = $request->filled('first_name')
            ? trim($request->string('first_name')->value())
            : null;
        $last = $request->filled('last_name')
            ? trim($request->string('last_name')->value())
            : null;

        if ($first === null || $first === '') {
            $parts = preg_split('/\s+/', trim($request->string('name')->value()), 2);
            $first = $parts[0] !== '' ? $parts[0] : trim($request->string('name')->value());
            $last ??= $parts[1] ?? null;
        }

        $phone = $request->filled('phone')
            ? preg_replace('/\s+/', '', $request->string('phone')->value())
            : null;

        return new self(
            firstName: $first,
            lastName: $last !== '' ? $last : null,
            // Username dinormalkan huruf kecil seperti email: unique di
            // database peka huruf tergantung collation.
            username: $request->filled('username')
                ? mb_strtolower(trim($request->string('username')->value()))
                : null,
            // Email dinormalkan huruf kecil: unique di database peka huruf
            // tergantung collation, dan "A@x.com" vs "a@x.com" harus dianggap
            // satu orang.
            email: mb_strtolower(trim($request->string('email')->value())),
            phone: $phone !== '' ? $phone : null,
            password: $request->string('password')->value(),
            city: $request->filled('city') ? trim($request->string('city')->value()) : null,
            province: $request->filled('province') ? trim($request->string('province')->value()) : null,
        );
    }

    /** Nama tampilan tersinkron untuk kolom `name` warisan. */
    public function displayName(): string
    {
        return trim($this->firstName.' '.($this->lastName ?? ''));
    }
}
