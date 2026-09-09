<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Bid;

use App\Actions\Bid\AcceptBidAction;
use App\Http\Resources\Api\V1\TaskResource;
use App\Models\Bid;
use Illuminate\Http\Request;

/** DEAL — pemberi kerja memilih satu penawaran. */
final class AcceptBidController
{
    public function __construct(private readonly AcceptBidAction $action) {}

    public function __invoke(Request $request, Bid $bid): TaskResource
    {
        return TaskResource::make(
            $this->action
                ->handle($bid, $request->user())
                ->load(['category', 'poster', 'workers', 'skills', 'payment']),
        );
    }
}
