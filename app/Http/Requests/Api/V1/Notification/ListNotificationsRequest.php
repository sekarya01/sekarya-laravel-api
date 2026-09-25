<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Notification;

use App\Http\Requests\Api\V1\Concerns\PaginatesWithCursor;
use Illuminate\Foundation\Http\FormRequest;

final class ListNotificationsRequest extends FormRequest
{
    use PaginatesWithCursor;

    /** @return array<string, mixed> */
    protected function filters(): array
    {
        return [
            // Hanya yang belum dibaca. Kesetaraan `read_at IS NULL` pada
            // indeks (user_id, read_at, created_at).
            'unread' => ['sometimes', 'boolean'],
        ];
    }
}
