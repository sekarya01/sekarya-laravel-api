<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Http\Requests\Api\V1\Auth\ResendCodeRequest;
use App\Support\Email;

final readonly class ResendCodeData
{
    public function __construct(public string $email) {}

    public static function fromRequest(ResendCodeRequest $request): self
    {
        return new self(Email::normalize($request->string('email')->value()));
    }
}
