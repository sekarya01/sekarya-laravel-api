<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Verification;

use App\Actions\Admin\Verification\ViewVerificationAction;
use App\Http\Resources\Api\V1\Admin\AdminVerificationDetailResource;
use App\Models\UserVerification;
use Illuminate\Http\Request;

/**
 * [#impure] [#io: db] [#warn: GET menulis jejak verification.viewed]
 * Detail pengajuan. Pembacaannya dicatat — lihat ViewVerificationAction.
 */
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
