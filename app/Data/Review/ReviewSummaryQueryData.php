<?php

declare(strict_types=1);

namespace App\Data\Review;

use App\Enums\ReviewerRole;
use App\Http\Requests\Api\V1\Review\ShowReviewSummaryRequest;

final readonly class ReviewSummaryQueryData
{
    public function __construct(public ?ReviewerRole $role = null) {}

    public static function fromRequest(ShowReviewSummaryRequest $request): self
    {
        return new self(
            role: $request->filled('role')
                ? ReviewerRole::from($request->string('role')->value())
                : null,
        );
    }
}
