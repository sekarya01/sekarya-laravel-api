<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Bid;

use App\Http\Requests\Api\V1\Concerns\PaginatesWithCursor;
use Illuminate\Foundation\Http\FormRequest;

final class ListTaskBidsRequest extends FormRequest
{
    use PaginatesWithCursor;

    /** @return array<string, mixed> */
    protected function filters(): array
    {
        // Pemberi kerja menimbang dari beberapa sudut: harga, reputasi, kebaruan.
        return [
            'sort' => ['sometimes', 'in:amount,rating,newest'],
        ];
    }

    public function sort(): string
    {
        return $this->string('sort', 'amount')->value();
    }
}
