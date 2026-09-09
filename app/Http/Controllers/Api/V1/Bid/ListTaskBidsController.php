<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Bid;

use App\Actions\Bid\ListBidsAction;
use App\Http\Requests\Api\V1\Bid\ListTaskBidsRequest;
use App\Http\Resources\Api\V1\BidResource;
use App\Models\Task;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Daftar penawaran — bahan pertimbangan pemberi kerja. */
final class ListTaskBidsController
{
    public function __construct(private readonly ListBidsAction $action) {}

    public function __invoke(ListTaskBidsRequest $request, Task $task): AnonymousResourceCollection
    {
        return BidResource::collection(
            $this->action->forTask($task, $request->page(), $request->sort()),
        );
    }
}
