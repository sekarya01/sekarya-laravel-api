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
    /** [#impure] [#io: db, fcm] [#warn: dispatch tanpa afterCommit — panggil sesudah commit] */
    public function handle(Task $task): int
    {
        if ($task->latitude === null || $task->longitude === null) {
            return 0;
        }

        $radius = (float) config('sekarya.tasks.notify_radius_km');
        $box = GeoDistance::boundingBox((float) $task->latitude, (float) $task->longitude, $radius);

        // Bounding box dulu (indeks `user_workers (latitude, longitude)`),
        // haversine kedua di PHP atas baris yang tersisa — bukan memuat semua
        // pekerja tersedia di setiap `POST /tasks`.
        $rows = UserWorker::query()
            ->where('is_available', true)
            ->whereBetween('latitude', $box['lat'])
            ->whereBetween('longitude', $box['lng'])
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
            (int) config('sekarya.tasks.notify_max_workers'),
        );

        if ($ids !== []) {
            NotifyNearbyWorkersJob::dispatch((string) $task->ulid, $ids);
        }

        return count($ids);
    }
}
