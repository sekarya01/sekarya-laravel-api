<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\SubmitVerificationAction;
use App\Data\User\SubmitVerificationData;
use App\Http\Requests\Api\V1\User\SubmitVerificationRequest;
use App\Http\Resources\Api\V1\VerificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class SubmitVerificationController
{
    public function __construct(private readonly SubmitVerificationAction $action) {}

    public function __invoke(SubmitVerificationRequest $request): JsonResponse
    {
        $verification = $this->action->handle(
            SubmitVerificationData::fromRequest($request),
            $request->user(),
        );

        return VerificationResource::make($verification)
            ->response()
            ->setStatusCode(Response::HTTP_ACCEPTED);
    }
}
