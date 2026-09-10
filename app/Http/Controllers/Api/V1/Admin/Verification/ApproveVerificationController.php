<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Verification;

use App\Actions\Admin\Verification\ReviewVerificationAction;
use App\Data\Admin\ReviewVerificationData;
use App\Http\Resources\Api\V1\Admin\AdminVerificationDetailResource;
use App\Models\UserVerification;
use Illuminate\Http\Request;

final class ApproveVerificationController
{
    public function __construct(private readonly ReviewVerificationAction $action) {}

    public function __invoke(Request $request, UserVerification $verification): AdminVerificationDetailResource
    {
        return AdminVerificationDetailResource::make(
            $this->action->handle(
                $verification,
                $request->user(),
                // Keputusannya ditentukan ENDPOINT-nya, bukan payload —
                // kalau dari payload, izin per-endpoint tidak berarti apa-apa.
                ReviewVerificationData::approve($request),
            )->load(['user', 'reviewer']),
        );
    }
}
