<?php

declare(strict_types=1);

namespace App\Data\Activity;

use App\Enums\DisputeCategory;
use App\Http\Requests\Api\V1\Activity\RaiseActivityDisputeRequest;

final readonly class RaiseActivityDisputeData
{
    /** @param list<string> $evidencePhotos */
    public function __construct(
        public DisputeCategory $category,
        public string $reason,
        public array $evidencePhotos = [],
    ) {}

    public static function fromRequest(RaiseActivityDisputeRequest $request): self
    {
        return new self(
            category: DisputeCategory::from($request->string('category')->value()),
            reason: trim($request->string('reason')->value()),
            evidencePhotos: array_values($request->array('evidence_photos')),
        );
    }
}
