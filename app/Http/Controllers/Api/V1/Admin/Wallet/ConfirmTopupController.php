<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Wallet;

use App\Actions\Admin\Wallet\ConfirmTopupAction;
use App\Http\Resources\Api\V1\Admin\AdminWalletTopupResource;
use App\Models\WalletTopup;
use Illuminate\Http\Request;

/** Dana terlihat di mutasi → saldo bertambah. Satu-satunya jalannya. */
final class ConfirmTopupController
{
    public function __construct(private readonly ConfirmTopupAction $action) {}

    public function __invoke(Request $request, WalletTopup $topup): AdminWalletTopupResource
    {
        return AdminWalletTopupResource::make(
            $this->action->handle($topup, $request->user(), $request->ip())->load('user'),
        );
    }
}
