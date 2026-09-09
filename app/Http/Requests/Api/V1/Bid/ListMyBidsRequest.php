<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Bid;

use App\Http\Requests\Api\V1\Concerns\PaginatesWithCursor;
use Illuminate\Foundation\Http\FormRequest;

final class ListMyBidsRequest extends FormRequest
{
    use PaginatesWithCursor;

    /** @return array<string, mixed> */
    protected function filters(): array
    {
        return [];
    }
}
