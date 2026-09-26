<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * Angka kotak masuk. `unread-count` mengeluarkan `{count}` (belum dibaca);
 * `read-all` mengeluarkan `{marked}` (berapa yang baru saja ditandai).
 */
final class NotificationCountResource extends BaseResource
{
    /** @return array<string, int> */
    public function toArray(Request $request): array
    {
        return array_map(static fn (mixed $n): int => (int) $n, (array) $this->resource);
    }
}
