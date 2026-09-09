<?php

declare(strict_types=1);

namespace App\Data\Auth;

use App\Http\Requests\Api\V1\Auth\ResendCodeRequest;

final readonly class ResendCodeData
{
    public function __construct(public string $email) {}

    public static function fromRequest(ResendCodeRequest $request): self
    {
        return new self(mb_strtolower(trim($request->string('email')->value())));
    }
}
