<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Actions\Wallet\ListWithdrawalsAction;
use App\Enums\WalletWithdrawalStatus;
use App\Http\Requests\Api\V1\Wallet\ListWithdrawalsRequest;
use App\Http\Resources\Api\V1\WalletWithdrawalResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListWithdrawalsController
{
    public function __construct(private readonly ListWithdrawalsAction $action) {}

    public function __invoke(ListWithdrawalsRequest $request): AnonymousResourceCollection
    {
        return WalletWithdrawalResource::collection($this->action->handle(
            $request->user(),
            $request->page(),
            $request->filled('status')
                ? WalletWithdrawalStatus::from($request->string('status')->value())
                : null,
        ));
    }
}
