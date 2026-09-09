<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\VerifyEmailAction;
use App\Data\Auth\VerifyEmailData;
use App\Http\Requests\Api\V1\Auth\VerifyEmailRequest;
use App\Http\Resources\Api\V1\TokenPairResource;

/** Verifikasi kode, aktifkan akun, terbitkan pasangan token. */
final class VerifyEmailController
{
    public function __construct(private readonly VerifyEmailAction $action) {}

    public function __invoke(VerifyEmailRequest $request): TokenPairResource
    {
        return new TokenPairResource(
            $this->action->handle(VerifyEmailData::fromRequest($request)),
        );
    }
}
