<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Satu baris kotak masuk.
 *
 * `task_id`/`activity_id` diangkat dari `data` supaya klien tidak perlu
 * mengurai payload untuk kasus paling umum (membuka Detail). `data` tetap
 * dikirim utuh — isinya sama persis dengan `data` push FCM — untuk kunci khas
 * satu jenis peristiwa (`cancel_request_id`, `result`, `bids_count`). Selalu
 * objek (`{}` bila kosong), tidak pernah `[]`.
 *
 * @mixin UserNotification
 */
final class UserNotificationResource extends BaseResource
{
    /**
     * Dipaksa ber-envelope `{data}`.
     *
     * Laravel melewati pembungkusan otomatis begitu hasil `toArray()` sudah
     * punya kunci bernama sama dengan wrapper-nya (`data`) — dan baris
     * notifikasi memang membawa `data` (payload deep-link, sama dengan FCM).
     * Tanpa override ini, satu endpoint membalas objek telanjang sementara
     * daftarnya ber-envelope, dan klien harus tahu bedanya.
     */
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse(['data' => $this->resolve($request)]);
    }

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
            'data' => (object) $data,
            'read_at' => $this->iso($this->read_at),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
