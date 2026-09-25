<?php

declare(strict_types=1);

namespace App\Data\Review;

use App\Enums\ReviewTag;
use App\Http\Requests\Api\V1\Review\CreateReviewRequest;

final readonly class CreateReviewData
{
    /** @var list<ReviewTag> */
    public array $tags;

    /**
     * @param  list<ReviewTag>  $tags
     */
    public function __construct(
        public int $rating,
        public ?string $comment = null,
        /** ULID pekerja yang dinilai; null = biarkan Action yang menyimpulkan. */
        public ?string $workerUlid = null,
        array $tags = [],
    ) {
        // Duplikat dibuang di sini juga, bukan hanya di aturan `distinct`:
        // DTO harus aman saat Action dipanggil dari luar jalur HTTP.
        $unique = [];

        foreach ($tags as $tag) {
            $unique[$tag->value] = $tag;
        }

        $this->tags = array_values($unique);
    }

    public static function fromRequest(CreateReviewRequest $request): self
    {
        /** @var list<string> $tags */
        $tags = array_values((array) $request->input('tags', []));

        return new self(
            rating: $request->integer('rating'),
            comment: $request->filled('comment')
                ? trim($request->string('comment')->value())
                : null,
            workerUlid: $request->filled('worker_id')
                ? trim($request->string('worker_id')->value())
                : null,
            tags: array_map(static fn (string $tag): ReviewTag => ReviewTag::from($tag), $tags),
        );
    }
}
