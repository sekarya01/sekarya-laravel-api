<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Actions\Wallet\ListTopupsAction;
use App\Enums\WalletTopupStatus;
use App\Http\Requests\Api\V1\Wallet\ListTopupsRequest;
use App\Http\Resources\Api\V1\WalletTopupResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListTopupsController
{
    public function __construct(private readonly ListTopupsAction $action) {}

    public function __invoke(ListTopupsRequest $request): AnonymousResourceCollection
    {
        return WalletTopupResource::collection($this->action->handle(
            $request->user(),
            $request->page(),
            $request->filled('status')
                ? WalletTopupStatus::from($request->string('status')->value())
                : null,
        ));
    }
}
