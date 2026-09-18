<?php

declare(strict_types=1);

namespace App\Data\Task;

use App\Http\Requests\Api\V1\Task\UpdateTaskRequest;

/**
 * Muatan penyuntingan task — PARSIAL.
 *
 * Nilai null saja tidak cukup untuk menyatakan maksud klien: `budget_max: null`
 * berarti "hapus batas atas", sedangkan `budget_max` yang tidak dikirim berarti
 * "jangan disentuh". Karena itu DTO ini membawa daftar ruas yang benar-benar
 * ada di badan permintaan, dan Action membacanya lewat `has()`.
 */
final readonly class UpdateTaskData
{
    /**
     * @param  list<string>  $provided  nama ruas yang benar-benar dikirim klien
     * @param  array<int, array<string, mixed>>  $options
     * @param  list<string>  $photos
     * @param  list<string>  $skillSlugs
     */
    private function __construct(
        public array $provided,
        public ?int $categoryId = null,
        public ?string $title = null,
        public ?string $description = null,
        public ?int $budgetMin = null,
        public ?int $budgetMax = null,
        public array $options = [],
        public array $photos = [],
        public ?string $locationText = null,
        public ?string $city = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public bool $isRemote = false,
        public ?int $workersNeeded = null,
        public ?string $neededAt = null,
        public ?string $endAt = null,
        public ?string $biddingClosesAt = null,
        public array $skillSlugs = [],
    ) {}

    /** Ruas ini dikirim klien? Hanya yang dikirim yang boleh menimpa nilai lama. */
    public function has(string $field): bool
    {
        return in_array($field, $this->provided, true);
    }

    /** Tidak ada satu pun ruas yang dikirim — permintaan kosong. */
    public function isEmpty(): bool
    {
        return $this->provided === [];
    }

    public static function fromRequest(UpdateTaskRequest $request): self
    {
        /** @var list<string> $fields */
        $fields = [
            'category_id', 'title', 'description', 'budget_min', 'budget_max',
            'options', 'photos', 'location_text', 'city', 'latitude', 'longitude',
            'is_remote', 'workers_needed', 'needed_at', 'end_at', 'bidding_closes_at',
            'skills',
        ];

        $provided = array_values(array_filter(
            $fields,
            static fn (string $field): bool => $request->has($field),
        ));

        return new self(
            provided: $provided,
            categoryId: $request->filled('category_id') ? $request->integer('category_id') : null,
            title: $request->has('title') ? trim($request->string('title')->value()) : null,
            description: $request->has('description')
                ? trim($request->string('description')->value())
                : null,
            budgetMin: $request->filled('budget_min') ? $request->integer('budget_min') : null,
            budgetMax: $request->filled('budget_max') ? $request->integer('budget_max') : null,
            options: $request->array('options'),
            photos: array_values($request->array('photos')),
            locationText: $request->filled('location_text')
                ? trim($request->string('location_text')->value())
                : null,
            city: $request->has('city') ? trim($request->string('city')->value()) : null,
            latitude: $request->filled('latitude') ? $request->float('latitude') : null,
            longitude: $request->filled('longitude') ? $request->float('longitude') : null,
            isRemote: $request->boolean('is_remote'),
            workersNeeded: $request->filled('workers_needed')
                ? $request->integer('workers_needed')
                : null,
            neededAt: $request->filled('needed_at')
                ? $request->string('needed_at')->value()
                : null,
            endAt: $request->filled('end_at') ? $request->string('end_at')->value() : null,
            biddingClosesAt: $request->filled('bidding_closes_at')
                ? $request->string('bidding_closes_at')->value()
                : null,
            skillSlugs: array_values(array_filter(array_map('trim', $request->array('skills')))),
        );
    }
}
