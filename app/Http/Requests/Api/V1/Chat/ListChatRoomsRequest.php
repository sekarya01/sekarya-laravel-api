<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Chat;

use App\Http\Requests\Api\V1\Concerns\PaginatesWithCursor;
use Illuminate\Foundation\Http\FormRequest;

final class ListChatRoomsRequest extends FormRequest
{
    use PaginatesWithCursor;

    /** @return array<string, mixed> */
    protected function filters(): array
    {
        return [
            // `deactivated` sengaja tidak bisa diminta: room itu sudah tidak ada.
            'status' => ['sometimes', 'string', 'in:active,expired'],
        ];
    }
}
