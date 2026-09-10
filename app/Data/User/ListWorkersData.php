<?php

declare(strict_types=1);

namespace App\Data\User;

use App\Data\CursorPageData;
use App\Enums\Gender;
use App\Http\Requests\Api\V1\User\ListWorkersRequest;

final readonly class ListWorkersData
{
    public function __construct(
        public CursorPageData $page,
        public ?string $city = null,
        public ?string $province = null,
        public ?Gender $gender = null,
        public ?bool $readyToWork = null,
    ) {}

    public static function fromRequest(ListWorkersRequest $request): self
    {
        return new self(
            page: $request->page(),
            city: $request->filled('city') ? trim($request->string('city')->value()) : null,
            province: $request->filled('province') ? trim($request->string('province')->value()) : null,
            gender: $request->filled('gender')
                ? Gender::from($request->string('gender')->value())
                : null,
            // `has()`, bukan `filled()`: `ready_to_work=0` adalah permintaan
            // yang sah — "tunjukkan yang BELUM terverifikasi" — dan `filled()`
            // pada nilai "0" mudah salah dibaca saat aturannya berubah.
            readyToWork: $request->has('ready_to_work')
                ? $request->boolean('ready_to_work')
                : null,
        );
    }
}
