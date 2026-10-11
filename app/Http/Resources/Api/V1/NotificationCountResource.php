<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * Satu angka hitungan `{count}` — kini hanya `GET chat/unread-count` (pesan
 * chat belum dibaca). Kotak masuk notifikasi server sudah dihapus
 * (2026-10-10), jadi namanya tinggal sejarah.
 */
final class NotificationCountResource extends BaseResource
{
    /** @return array<string, int> */
    public function toArray(Request $request): array
    {
        return array_map(static fn (mixed $n): int => (int) $n, (array) $this->resource);
    }
}
