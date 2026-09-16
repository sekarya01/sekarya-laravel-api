<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Wallet;

use App\Actions\Wallet\ListWalletEntriesAction;
use App\Data\Wallet\WalletEntryQueryData;
use App\Http\Requests\Api\V1\Wallet\ListWalletEntriesRequest;
use App\Http\Resources\Api\V1\WalletEntryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListWalletEntriesController
{
    public function __construct(private readonly ListWalletEntriesAction $action) {}

    public function __invoke(ListWalletEntriesRequest $request): AnonymousResourceCollection
    {
        return WalletEntryResource::collection(
            $this->action->handle($request->user(), WalletEntryQueryData::fromRequest($request)),
        );
    }
}
