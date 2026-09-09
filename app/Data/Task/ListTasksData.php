<?php

declare(strict_types=1);

namespace App\Data\Task;

use App\Data\CursorPageData;
use App\Enums\TaskStatus;
use App\Http\Requests\Api\V1\Task\ListTasksRequest;

final readonly class ListTasksData
{
    /** Radius default kalau koordinat dikirim tanpa radius. */
    public const float DEFAULT_RADIUS_KM = 10.0;

    public const float MAX_RADIUS_KM = 100.0;

    /** @param list<string> $skillSlugs */
    public function __construct(
        public CursorPageData $page,
        public ?TaskStatus $status = null,
        public ?int $categoryId = null,
        public ?string $city = null,
        public ?int $budgetFrom = null,
        public ?int $budgetTo = null,
        public bool $excludeMyBids = false,
        // Waktu — hanya task yang diposting dalam sekian jam terakhir.
        public ?int $postedWithinHours = null,
        // Kata kunci — lewat indeks terbalik FULLTEXT, bukan LIKE.
        public ?string $keyword = null,
        // Jarak — filter radius; urutan default tetap tanggal pembuatan.
        public ?float $latitude = null,
        public ?float $longitude = null,
        public float $radiusKm = self::DEFAULT_RADIUS_KM,
        // Keahlian — slug, dicocokkan lewat pivot berindeks.
        public array $skillSlugs = [],
        public bool $matchMySkills = false,
    ) {}

    public static function fromRequest(ListTasksRequest $request): self
    {
        return new self(
            page: $request->page(),
            status: $request->filled('status')
                ? TaskStatus::from($request->string('status')->value())
                : null,
            categoryId: $request->filled('category_id') ? $request->integer('category_id') : null,
            city: $request->filled('city') ? trim($request->string('city')->value()) : null,
            budgetFrom: $request->filled('budget_from') ? $request->integer('budget_from') : null,
            budgetTo: $request->filled('budget_to') ? $request->integer('budget_to') : null,
            excludeMyBids: $request->boolean('exclude_my_bids'),
            postedWithinHours: $request->filled('posted_within_hours')
                ? max(1, $request->integer('posted_within_hours'))
                : null,
            keyword: $request->filled('q') ? trim($request->string('q')->value()) : null,
            latitude: $request->filled('lat') ? $request->float('lat') : null,
            longitude: $request->filled('lng') ? $request->float('lng') : null,
            // Di-clamp di sini juga: DTO harus aman saat dipakai di luar HTTP.
            radiusKm: min(
                max($request->float('radius_km', self::DEFAULT_RADIUS_KM), 0.1),
                self::MAX_RADIUS_KM,
            ),
            skillSlugs: array_values(array_filter(array_map(
                'trim',
                $request->has('skills')
                    ? (is_array($request->input('skills'))
                        ? $request->input('skills')
                        : explode(',', (string) $request->input('skills')))
                    : [],
            ))),
            matchMySkills: $request->boolean('match_my_skills'),
        );
    }

    /** Radius hanya berlaku kalau koordinat lengkap. */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
