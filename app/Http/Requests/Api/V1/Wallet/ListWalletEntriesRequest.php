<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Wallet;

use App\Enums\WalletEntryDirection;
use App\Enums\WalletEntryType;
use App\Http\Requests\Api\V1\Concerns\PaginatesWithCursor;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListWalletEntriesRequest extends FormRequest
{
    use PaginatesWithCursor;

    /** @return array<string, mixed> */
    protected function filters(): array
    {
        return [
            'type' => ['sometimes', Rule::enum(WalletEntryType::class)],
            'direction' => ['sometimes', Rule::enum(WalletEntryDirection::class)],
        ];
    }
}
