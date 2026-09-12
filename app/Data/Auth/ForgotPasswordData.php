<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;

final readonly class ForgotPasswordData
{
    public function __construct(public string $email) {}

    public static function fromRequest(ForgotPasswordRequest $request): self
    {
        return new self(
            // Normalisasi sama seperti register/login: unique di database
            // peka huruf tergantung collation.
            email: mb_strtolower(trim($request->string('email')->value())),
        );
    }
}
