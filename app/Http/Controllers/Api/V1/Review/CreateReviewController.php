<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Review;

use App\Actions\Review\CreateReviewAction;
use App\Data\Review\CreateReviewData;
use App\Http\Requests\Api\V1\Review\CreateReviewRequest;
use App\Http\Resources\Api\V1\ReviewResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class CreateReviewController
{
    public function __construct(private readonly CreateReviewAction $action) {}

    public function __invoke(CreateReviewRequest $request, Task $task): JsonResponse
    {
        $review = $this->action->handle(
            CreateReviewData::fromRequest($request),
            $task,
            $request->user(),
        );

        return ReviewResource::make($review->load('reviewer'))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
