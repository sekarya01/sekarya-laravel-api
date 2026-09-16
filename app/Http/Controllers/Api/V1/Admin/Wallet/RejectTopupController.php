<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Wallet;

use App\Actions\Admin\Wallet\RejectTopupAction;
use App\Data\Admin\RejectWalletRequestData;
use App\Http\Requests\Api\V1\Admin\RejectWalletRequestRequest;
use App\Http\Resources\Api\V1\Admin\AdminWalletTopupResource;
use App\Models\WalletTopup;

final class RejectTopupController
{
    public function __construct(private readonly RejectTopupAction $action) {}

    public function __invoke(RejectWalletRequestRequest $request, WalletTopup $topup): AdminWalletTopupResource
    {
        return AdminWalletTopupResource::make($this->action->handle(
            $topup,
            $request->user(),
            RejectWalletRequestData::fromRequest($request),
        )->load('user'));
    }
}
