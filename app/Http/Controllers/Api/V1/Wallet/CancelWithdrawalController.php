<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Actions\Wallet\CancelWithdrawalAction;
use App\Http\Resources\Api\V1\WalletWithdrawalResource;
use App\Models\WalletWithdrawal;
use Illuminate\Http\Request;

final class CancelWithdrawalController
{
    public function __construct(private readonly CancelWithdrawalAction $action) {}

    public function __invoke(Request $request, WalletWithdrawal $withdrawal): WalletWithdrawalResource
    {
        return WalletWithdrawalResource::make(
            $this->action->handle($withdrawal)->load('verification'),
        );
    }
}
