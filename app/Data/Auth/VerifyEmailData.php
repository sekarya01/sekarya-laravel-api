<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Http\Requests\Api\V1\Auth\VerifyEmailRequest;

final readonly class VerifyEmailData
{
    public function __construct(
        public string $email,
        public string $code,
    ) {}

    public static function fromRequest(VerifyEmailRequest $request): self
    {
        return new self(
            email: mb_strtolower(trim($request->string('email')->value())),
            // Buang spasi dan tanda pisah: orang sering menyalin "123 456".
            code: preg_replace('/\D/', '', $request->string('code')->value()) ?? '',
        );
    }
}
