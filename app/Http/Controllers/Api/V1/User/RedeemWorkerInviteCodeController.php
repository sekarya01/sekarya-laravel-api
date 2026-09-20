<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\RedeemWorkerInviteCodeAction;
use App\Http\Requests\Api\V1\User\RedeemWorkerInviteCodeRequest;
use App\Http\Resources\Api\V1\WorkerInviteRedemptionResource;

final class RedeemWorkerInviteCodeController
{
    public function __construct(private readonly RedeemWorkerInviteCodeAction $action) {}

    public function __invoke(RedeemWorkerInviteCodeRequest $request): WorkerInviteRedemptionResource
    {
        $result = $this->action->handle(
            (string) $request->validated()['code'],
            $request->user(),
        );

        return WorkerInviteRedemptionResource::make($result['code']);
    }
}
