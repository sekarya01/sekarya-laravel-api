<?php

declare(strict_types=1);

namespace App\Data\Task;

use App\Http\Requests\Api\V1\Task\RaiseDisputeRequest;

/** Payload pengajuan kendala (G5). */
final readonly class RaiseDisputeData
{
    /** @param list<string> $evidencePhotos */
    public function __construct(
        public string $reason,
        public array $evidencePhotos = [],
    ) {}

    public static function fromRequest(RaiseDisputeRequest $request): self
    {
        /** @var list<string> $photos */
        $photos = array_values((array) $request->input('evidence_photos', []));

        return new self(
            reason: $request->string('reason')->value(),
            evidencePhotos: $photos,
        );
    }
}
