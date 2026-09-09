<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Review;

use App\Actions\Review\ListUserReviewsAction;
use App\Http\Requests\Api\V1\Review\ListUserReviewsRequest;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Models\User;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Penilaian yang DITERIMA seseorang, bisa dipisah per peran. */
final class ListUserReviewsController
{
    public function __construct(private readonly ListUserReviewsAction $action) {}

    public function __invoke(ListUserReviewsRequest $request, User $user): AnonymousResourceCollection
    {
        return ReviewResource::collection(
            $this->action->forUser($user, $request->page(), $request->role()),
        );
    }
}
