<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Bid;

use App\Actions\Bid\ListBidsAction;
use App\Http\Requests\Api\V1\Bid\ListMyBidsRequest;
use App\Http\Resources\Api\V1\BidResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListMyBidsController
{
    public function __construct(private readonly ListBidsAction $action) {}

    public function __invoke(ListMyBidsRequest $request): AnonymousResourceCollection
    {
        return BidResource::collection(
            $this->action->byBidder($request->user(), $request->page()),
        );
    }
}
