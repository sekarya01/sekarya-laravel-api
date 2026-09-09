<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Bid;

use App\Actions\Bid\PlaceBidAction;
use App\Data\Bid\PlaceBidData;
use App\Http\Requests\Api\V1\Bid\PlaceBidRequest;
use App\Http\Resources\Api\V1\BidResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class PlaceBidController
{
    public function __construct(private readonly PlaceBidAction $action) {}

    public function __invoke(PlaceBidRequest $request, Task $task): JsonResponse
    {
        $bid = $this->action->handle(
            PlaceBidData::fromRequest($request),
            $task,
            $request->user(),
        );

        return BidResource::make($bid->load('bidder'))
            ->response()
            ->setStatusCode($bid->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
