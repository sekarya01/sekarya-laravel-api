<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\UserReport;
use Illuminate\Http\Request;

/**
 * Laporan pengguna (G7) untuk pemiliknya / antrean admin.
 *
 * @mixin UserReport
 */
final class UserReportResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'reason' => $this->reason->value,
            'note' => $this->note,
            'status' => $this->status->value,
            'reporter' => $this->whenLoaded('reporter', fn (): array => [
                'id' => $this->reporter->ulid,
                'name' => $this->reporter->name,
            ]),
            'reported' => $this->whenLoaded('reported', fn (): array => [
                'id' => $this->reported->ulid,
                'name' => $this->reported->name,
            ]),
            'task' => $this->whenLoaded('task', fn (): ?array => $this->task === null ? null : [
                'id' => $this->task->ulid,
                'title' => $this->task->title,
            ]),
            'created_at' => $this->iso($this->created_at),
            'reviewed_at' => $this->iso($this->reviewed_at),
        ];
    }
}
