<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * Hitungan task per status (B7). Selalu objek dengan SETIAP kunci
 * `TaskStatus` — nol bila tidak ada baris.
 */
final class TaskStatusCountsResource extends BaseResource
{
    /** @return array<string, int> */
    public function toArray(Request $request): array
    {
        /** @var array<string, int> $counts */
        $counts = (array) $this->resource;

        return $counts;
    }
}
