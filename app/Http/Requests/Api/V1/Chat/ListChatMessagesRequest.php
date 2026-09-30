<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use App\Http\Requests\Api\V1\Concerns\PaginatesWithCursor;
use Illuminate\Foundation\Http\FormRequest;

final class ListChatMessagesRequest extends FormRequest
{
    use PaginatesWithCursor;

    /** @return array<string, mixed> */
    protected function filters(): array
    {
        return [
            // Sinkron sesudah push: hanya pesan yang LEBIH BARU dari id ini.
            'after_id' => ['sometimes', 'string', 'size:26'],
        ];
    }
}
