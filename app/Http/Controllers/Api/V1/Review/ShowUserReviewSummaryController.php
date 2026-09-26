<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Review;

use App\Actions\Review\SummarizeUserReviewsAction;
use App\Data\Review\ReviewSummaryQueryData;
use App\Http\Requests\Api\V1\Review\ShowReviewSummaryRequest;
use App\Http\Resources\Api\V1\ReviewSummaryResource;
use App\Models\User;

/** Rata-rata, jumlah, dan sebaran bintang ulasan yang diterima seseorang. */
final class ShowUserReviewSummaryController
{
    public function __construct(private readonly SummarizeUserReviewsAction $action) {}

    public function __invoke(ShowReviewSummaryRequest $request, User $user): ReviewSummaryResource
    {
        return ReviewSummaryResource::make(
            $this->action->handle($user, ReviewSummaryQueryData::fromRequest($request)),
        );
    }
}
