<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\UserNotification;
use Illuminate\Http\Request;

/**
 * Satu baris kotak masuk — persis bentuk pada kontrak dokumen:
 * `id, type, title, body, task_id, activity_id, read_at, created_at`.
 *
 * @mixin UserNotification
 */
final class UserNotificationResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array<string, string> $data */
        $data = $this->data ?? [];

        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'body' => $this->body,
            'task_id' => $data['task_id'] ?? null,
            'activity_id' => $data['activity_id'] ?? null,
            'read_at' => $this->iso($this->read_at),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
