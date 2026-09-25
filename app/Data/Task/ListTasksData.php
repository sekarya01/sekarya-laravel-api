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

    /** Batas `category_ids[]` — lihat ListTasksRequest. */
    public const int MAX_CATEGORY_IDS = 20;

    /**
     * @param  list<string>  $skillSlugs
     * @param  list<int>  $categoryIds  gabungan `category_id` dan `category_ids[]`
     * @param  list<TaskStatus>  $statuses  gabungan `status` dan `statuses[]`
     */
    public function __construct(
        public CursorPageData $page,
        public array $statuses = [],
        public array $categoryIds = [],
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
            // Bentuk tunggal lama dan bentuk daftar baru bermuara di SATU
            // field: Action tidak perlu tahu mana yang dikirim klien, dan
            // tidak ada dua cabang penyaring yang bisa berselisih.
            statuses: $request->filled('status')
                ? [TaskStatus::from($request->string('status')->value())]
                : array_values(array_map(
                    static fn (mixed $v): TaskStatus => TaskStatus::from((string) $v),
                    array_unique((array) $request->input('statuses', [])),
                )),
            categoryIds: $request->filled('category_id')
                ? [$request->integer('category_id')]
                : array_slice(array_values(array_unique(array_map(
                    'intval',
                    (array) $request->input('category_ids', []),
                ))), 0, self::MAX_CATEGORY_IDS),
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
