<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Wallet;

use App\Actions\Admin\Wallet\ListWithdrawalQueueAction;
use App\Data\Admin\WalletQueueData;
use App\Http\Requests\Api\V1\Admin\WalletWithdrawalQueueRequest;
use App\Http\Resources\Api\V1\Admin\AdminWalletWithdrawalResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListWithdrawalQueueController
{
    public function __construct(private readonly ListWithdrawalQueueAction $action) {}

    public function __invoke(WalletWithdrawalQueueRequest $request): AnonymousResourceCollection
    {
        return AdminWalletWithdrawalResource::collection(
            $this->action->handle(WalletQueueData::fromRequest($request)),
        );
    }
}
