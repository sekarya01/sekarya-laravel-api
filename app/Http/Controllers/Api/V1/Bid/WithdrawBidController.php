<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Bid;

use App\Actions\Bid\WithdrawBidAction;
use App\Http\Resources\Api\V1\BidResource;
use App\Models\Bid;

final class WithdrawBidController
{
    public function __construct(private readonly WithdrawBidAction $action) {}

    public function __invoke(Bid $bid): BidResource
    {
        return BidResource::make($this->action->handle($bid));
    }
}
