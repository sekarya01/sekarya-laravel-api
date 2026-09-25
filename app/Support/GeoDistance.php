<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Jarak antara lokasi task dan lokasi kerja seorang pekerja, dihitung SERVER.
 *
 * Dipakai `distance_km` di BidResource dan TaskResource.workers[] (U8). Ada
 * karena koordinat lokasi kerja pekerja (`user_workers.latitude/longitude`)
 * sengaja TIDAK pernah keluar dari API — klien tidak bisa menghitungnya
 * sendiri, dan tidak boleh diberi bahannya.
 *
 * Dua keputusan yang membatasi apa yang bisa disimpulkan dari angkanya:
 *
 * 1. **Koordinat pekerja dibulatkan 3 desimal (±110 m) SEBELUM dihitung.**
 *    Jarak presisi dari beberapa task berbeda cukup untuk trilaterasi titik
 *    lokasi kerja — yang bagi banyak pekerja adalah rumahnya. Dengan
 *    pembulatan ini yang bisa ditemukan paling banter kotak ±110 m, tingkat
 *    kekaburan yang sama dengan lokasi task sebelum deal (TaskResource).
 * 2. **Hasilnya 1 desimal** — sesuai tampilan ("1,1 km"), tidak lebih.
 *
 * Rumus haversine yang sama dengan `TaskSearch::haversineSql()` (jari-jari
 * bumi 6371 km). Yang itu berjalan di SQL untuk MENYARING feed; yang ini di
 * PHP untuk menampilkan jarak baris yang sudah dimuat, tanpa kueri tambahan.
 */
final class GeoDistance
{
    private const float EARTH_RADIUS_KM = 6371.0;

    /** Kekaburan koordinat pekerja — sama dengan lokasi task sebelum deal. */
    public const int WORKER_COORDINATE_DECIMALS = 3;

    /** `null` bila salah satu titik tidak lengkap. */
    public static function taskToWorkerKm(
        mixed $taskLatitude,
        mixed $taskLongitude,
        mixed $workerLatitude,
        mixed $workerLongitude,
    ): ?float {
        if ($taskLatitude === null || $taskLongitude === null
            || $workerLatitude === null || $workerLongitude === null) {
            return null;
        }

        return round(self::haversineKm(
            (float) $taskLatitude,
            (float) $taskLongitude,
            round((float) $workerLatitude, self::WORKER_COORDINATE_DECIMALS),
            round((float) $workerLongitude, self::WORKER_COORDINATE_DECIMALS),
        ), 1);
    }

    private static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        // min(): pembulatan floating point bisa membuat $a sedikit di atas 1.
        return 2 * self::EARTH_RADIUS_KM * asin(min(1.0, sqrt($a)));
    }
}
