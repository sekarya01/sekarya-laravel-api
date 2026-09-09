<?php

declare(strict_types=1);

namespace App\Data\Bid;

use App\Http\Requests\Api\V1\Bid\PlaceBidRequest;

final readonly class PlaceBidData
{
    /** @param array<int, array<string, mixed>> $optionResponses */
    public function __construct(
        public int $amount,
        public ?string $message = null,
        public array $optionResponses = [],
        public ?float $estimatedHours = null,
        public ?string $canStartAt = null,
    ) {}

    public static function fromRequest(PlaceBidRequest $request): self
    {
        return new self(
            amount: $request->integer('amount'),
            message: $request->filled('message')
                ? trim($request->string('message')->value())
                : null,
            optionResponses: $request->array('option_responses'),
            estimatedHours: $request->filled('estimated_hours')
                ? $request->float('estimated_hours')
                : null,
            canStartAt: $request->filled('can_start_at')
                ? $request->string('can_start_at')->value()
                : null,
        );
    }
}
