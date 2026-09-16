<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Actions\Wallet\CancelTopupAction;
use App\Http\Resources\Api\V1\WalletTopupResource;
use App\Models\WalletTopup;
use Illuminate\Http\Request;

final class CancelTopupController
{
    public function __construct(private readonly CancelTopupAction $action) {}

    public function __invoke(Request $request, WalletTopup $topup): WalletTopupResource
    {
        return WalletTopupResource::make($this->action->handle($topup));
    }
}
