<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\ChangePasswordAction;
use App\Data\Auth\ChangePasswordData;
use App\Http\Requests\Api\V1\Auth\ChangePasswordRequest;
use Illuminate\Http\Response;

/** Ganti kata sandi (G4). Balasan kosong; klien masuk ulang. */
final class ChangePasswordController
{
    public function __construct(private readonly ChangePasswordAction $action) {}

    public function __invoke(ChangePasswordRequest $request): Response
    {
        $this->action->handle(ChangePasswordData::fromRequest($request), $request->user());

        return response()->noContent();
    }
}
