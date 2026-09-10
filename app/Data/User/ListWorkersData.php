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
        );
    }
}
