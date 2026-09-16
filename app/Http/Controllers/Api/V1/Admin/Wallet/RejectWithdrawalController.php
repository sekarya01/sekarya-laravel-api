<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Wallet;

use App\Actions\Admin\Wallet\RejectWithdrawalAction;
use App\Data\Admin\RejectWalletRequestData;
use App\Http\Requests\Api\V1\Admin\RejectWalletRequestRequest;
use App\Http\Resources\Api\V1\Admin\AdminWalletWithdrawalResource;
use App\Models\WalletWithdrawal;

/** Ditolak → tahanan saldo dikembalikan. Lihat Action-nya. */
final class RejectWithdrawalController
{
    public function __construct(private readonly RejectWithdrawalAction $action) {}

    public function __invoke(
        RejectWalletRequestRequest $request,
        WalletWithdrawal $withdrawal,
    ): AdminWalletWithdrawalResource {
        return AdminWalletWithdrawalResource::make($this->action->handle(
            $withdrawal,
            $request->user(),
            RejectWalletRequestData::fromRequest($request),
        )->load(['user', 'verification']));
    }
}
