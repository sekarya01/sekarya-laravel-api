<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Enums\ReportReason;
use App\Http\Requests\Api\V1\User\ReportUserRequest;

/** Payload laporan pengguna (G7). */
final readonly class ReportUserData
{
    public function __construct(
        public ReportReason $reason,
        public ?string $note = null,
        public ?string $taskUlid = null,
    ) {}

    public static function fromRequest(ReportUserRequest $request): self
    {
        return new self(
            reason: ReportReason::from($request->string('reason')->value()),
            note: $request->filled('note') ? $request->string('note')->value() : null,
            taskUlid: $request->filled('task_id') ? $request->string('task_id')->value() : null,
        );
    }
}
