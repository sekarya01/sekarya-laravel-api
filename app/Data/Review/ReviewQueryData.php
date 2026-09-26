<?php

declare(strict_types=1);

namespace App\Data\Review;

use App\Data\CursorPageData;
use App\Enums\ReviewerRole;
use App\Http\Requests\Api\V1\Review\ListUserReviewsRequest;

/** Penyaring daftar ulasan yang DITERIMA seseorang. */
final readonly class ReviewQueryData
{
    public function __construct(
        public CursorPageData $page = new CursorPageData,
        public ?ReviewerRole $role = null,
        /** Tepat bintang ini. */
        public ?int $rating = null,
        /** Bintang ini ke bawah ("1-2★"). */
        public ?int $ratingMax = null,
        /** Kata kunci komentar; null = tanpa pencarian. */
        public ?string $keyword = null,
        /** Hanya yang berfoto (`true`) atau tanpa foto (`false`); null = semua. */
        public ?bool $hasPhotos = null,
    ) {}

    public static function fromRequest(ListUserReviewsRequest $request): self
    {
        $keyword = $request->filled('q') ? trim($request->string('q')->value()) : '';

        return new self(
            page: $request->page(),
            role: $request->filled('role')
                ? ReviewerRole::from($request->string('role')->value())
                : null,
            rating: $request->filled('rating') ? $request->integer('rating') : null,
            ratingMax: $request->filled('rating_max') ? $request->integer('rating_max') : null,
            keyword: $keyword === '' ? null : $keyword,
            hasPhotos: $request->filled('has_photos') ? $request->boolean('has_photos') : null,
        );
    }
}
