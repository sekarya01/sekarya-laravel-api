<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\UserStatus;
use App\Jobs\NotifyNearbyWorkersJob;
use App\Models\Task;
use App\Models\UserWorker;
use App\Support\GeoDistance;

/**
 * Hitung mitra tersedia di sekitar tugas, lalu antrekan kabarnya (B13).
 *
 * Mengembalikan JUMLAH penerima supaya `POST tasks` bisa menjawab
 * `meta.notified_workers` tanpa menghitung ulang. Yang disaring: punya lokasi
 * kerja, ketersediaannya menyala, akunnya aktif, dan bukan pemberi kerja itu
 * sendiri. Penerima dibatasi `notify_max_workers`.
 */
final class NotifyNearbyWorkersAction
{
    public function handle(Task $task): int
    {
        if ($task->latitude === null || $task->longitude === null) {
            return 0;
        }

        $radius = (float) config('sekarya.tasks.notify_radius_km', 5);

        $rows = UserWorker::query()
            ->where('is_available', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereHas('user', fn ($q) => $q
                ->where('status', UserStatus::Active)
                ->where('id', '!=', $task->poster_id))
            ->get(['user_id', 'latitude', 'longitude']);

        $ids = [];

        foreach ($rows as $worker) {
            $distance = GeoDistance::betweenKm(
                $task->latitude,
                $task->longitude,
                $worker->latitude,
                $worker->longitude,
            );

            if ($distance !== null && $distance <= $radius) {
                $ids[] = (int) $worker->user_id;
            }
        }

        $ids = array_slice(
            array_values(array_unique($ids)),
            0,
            (int) config('sekarya.tasks.notify_max_workers', 50),
        );

        if ($ids !== []) {
            NotifyNearbyWorkersJob::dispatch((string) $task->ulid, $ids);
        }

        return count($ids);
    }
}
