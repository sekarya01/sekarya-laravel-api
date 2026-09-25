<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Data\Review\ReviewSummary;
use Illuminate\Http\Request;

/**
 * Ringkasan ulasan seseorang.
 *
 * Nama kunci mengikuti `as_worker.rating_avg` / `rating_count` di
 * PublicUserResource, supaya dua angka yang sama tidak bernama berbeda.
 *
 * `distribution` SELALU objek dengan kunci "5".."1" (nol bila kosong) — tidak
 * pernah larik, yang akan terjadi kalau kuncinya kebetulan berurutan 0..n.
 *
 * @property ReviewSummary $resource
 */
final class ReviewSummaryResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $summary = $this->resource;

        return [
            'role' => $summary->role?->value,
            'rating_avg' => $summary->average,
            'rating_count' => $summary->count,
            'distribution' => (object) array_combine(
                array_map('strval', array_keys($summary->distribution)),
                array_values($summary->distribution),
            ),
            'five_star_percent' => $summary->fiveStarPercent,
        ];
    }
}
