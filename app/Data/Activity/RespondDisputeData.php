<?php

declare(strict_types=1);

namespace App\Data\Activity;

use App\Http\Requests\Api\V1\Activity\RespondDisputeRequest;

final readonly class RespondDisputeData
{
    /** @param list<string> $evidencePhotos */
    public function __construct(
        public string $response,
        public array $evidencePhotos = [],
    ) {}

    public static function fromRequest(RespondDisputeRequest $request): self
    {
        return new self(
            response: trim($request->string('response')->value()),
            evidencePhotos: array_values($request->array('evidence_photos')),
        );
    }
}
