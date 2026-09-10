<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Verification;

use App\Actions\Admin\Verification\ReviewVerificationAction;
use App\Data\Admin\ReviewVerificationData;
use App\Http\Requests\Api\V1\Admin\ReviewVerificationRequest;
use App\Http\Resources\Api\V1\Admin\AdminVerificationDetailResource;
use App\Models\UserVerification;

/** Menarik kembali verifikasi yang sudah diberikan. */
final class RevokeVerificationController
{
    public function __construct(private readonly ReviewVerificationAction $action) {}

    public function __invoke(
        ReviewVerificationRequest $request,
        UserVerification $verification,
    ): AdminVerificationDetailResource {
        return AdminVerificationDetailResource::make(
            $this->action->handle(
                $verification,
                $request->user(),
                ReviewVerificationData::revoke($request),
            )->load(['user', 'reviewer']),
        );
    }
}
