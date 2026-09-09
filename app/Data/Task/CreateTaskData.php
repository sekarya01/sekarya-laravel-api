<?php

declare(strict_types=1);

namespace App\Data\Task;

use App\Http\Requests\Api\V1\Task\CreateTaskRequest;

final readonly class CreateTaskData
{
    /**
     * @param  array<int, array<string, mixed>>  $options
     * @param  list<string>  $photos
     */
    public function __construct(
        public int $categoryId,
        public string $title,
        public string $description,
        public int $budgetMin,
        public ?int $budgetMax = null,
        public array $options = [],
        public array $photos = [],
        public ?string $locationText = null,
        public ?string $city = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public bool $isRemote = false,
        /** Jumlah pekerja yang dibutuhkan; juga kuota pelamar. */
        public int $workersNeeded = 1,
        public ?string $neededAt = null,
        public ?string $biddingClosesAt = null,
        public bool $publishNow = false,
        /** @var list<string> slug keahlian yang dibutuhkan */
        public array $skillSlugs = [],
    ) {}

    public static function fromRequest(CreateTaskRequest $request): self
    {
        return new self(
            categoryId: $request->integer('category_id'),
            title: trim($request->string('title')->value()),
            description: trim($request->string('description')->value()),
            budgetMin: $request->integer('budget_min'),
            // Sengaja tetap null kalau tidak dikirim — max itu opsional.
            budgetMax: $request->filled('budget_max') ? $request->integer('budget_max') : null,
            options: $request->array('options'),
            photos: $request->array('photos'),
            locationText: $request->filled('location_text')
                ? trim($request->string('location_text')->value())
                : null,
            city: $request->filled('city') ? trim($request->string('city')->value()) : null,
            latitude: $request->filled('latitude') ? $request->float('latitude') : null,
            longitude: $request->filled('longitude') ? $request->float('longitude') : null,
            isRemote: $request->boolean('is_remote'),
            workersNeeded: $request->filled('workers_needed')
                ? $request->integer('workers_needed')
                : 1,
            neededAt: $request->filled('needed_at') ? $request->string('needed_at')->value() : null,
            biddingClosesAt: $request->filled('bidding_closes_at')
                ? $request->string('bidding_closes_at')->value()
                : null,
            publishNow: $request->boolean('publish_now'),
            skillSlugs: array_values(array_filter(array_map('trim', $request->array('skills')))),
        );
    }
}
