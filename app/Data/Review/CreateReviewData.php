<?php

declare(strict_types=1);

namespace App\Data\Review;

use App\Http\Requests\Api\V1\Review\CreateReviewRequest;

final readonly class CreateReviewData
{
    public function __construct(
        public int $rating,
        public ?string $comment = null,
        /** ULID pekerja yang dinilai; null = biarkan Action yang menyimpulkan. */
        public ?string $workerUlid = null,
    ) {}

    public static function fromRequest(CreateReviewRequest $request): self
    {
        return new self(
            rating: $request->integer('rating'),
            comment: $request->filled('comment')
                ? trim($request->string('comment')->value())
                : null,
            workerUlid: $request->filled('worker_id')
                ? trim($request->string('worker_id')->value())
                : null,
        );
    }
}
