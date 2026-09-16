<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Wallet;

use App\Actions\Admin\Wallet\ListTopupQueueAction;
use App\Data\Admin\WalletQueueData;
use App\Http\Requests\Api\V1\Admin\WalletTopupQueueRequest;
use App\Http\Resources\Api\V1\Admin\AdminWalletTopupResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListTopupQueueController
{
    public function __construct(private readonly ListTopupQueueAction $action) {}

    public function __invoke(WalletTopupQueueRequest $request): AnonymousResourceCollection
    {
        return AdminWalletTopupResource::collection(
            $this->action->handle(WalletQueueData::fromRequest($request)),
        );
    }
}
