<?php

declare(strict_types=1);

namespace App\Data\Activity;

use App\Http\Requests\Api\V1\Activity\SubmitActivityRequest;

final readonly class SubmitActivityData
{
    /** @param list<string> $proofPhotos */
    public function __construct(
        public ?string $workerNote = null,
        public array $proofPhotos = [],
    ) {}

    public static function fromRequest(SubmitActivityRequest $request): self
    {
        return new self(
            workerNote: $request->filled('worker_note')
                ? trim($request->string('worker_note')->value())
                : null,
            proofPhotos: $request->array('proof_photos'),
        );
    }
}
