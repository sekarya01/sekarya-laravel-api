<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Http\Requests\Api\V1\User\DeleteAccountRequest;

/** Payload hapus akun (G3) — konfirmasi kata sandi. */
final readonly class DeleteAccountData
{
    public function __construct(public string $password) {}

    public static function fromRequest(DeleteAccountRequest $request): self
    {
        return new self(password: $request->string('password')->value());
    }
}
