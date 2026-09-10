<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Verification;

use App\Actions\Admin\Verification\ViewVerificationAction;
use App\Http\Resources\Api\V1\Admin\AdminVerificationDetailResource;
use App\Models\UserVerification;
use Illuminate\Http\Request;

/** Detail pengajuan. Pembacaannya dicatat — lihat ViewVerificationAction. */
final class ShowVerificationController
{
    public function __construct(private readonly ViewVerificationAction $action) {}

    public function __invoke(Request $request, UserVerification $verification): AdminVerificationDetailResource
    {
        return AdminVerificationDetailResource::make(
            $this->action->handle($verification, $request->user(), $request->ip()),
        );
    }
}
