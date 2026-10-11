<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\WorkerInviteCode;

/**
 * Apakah ada kode undangan mitra yang masih bisa dipakai di wilayah ini.
 *
 * Sinyal boolean saja — isi kodenya tidak pernah keluar: endpoint-nya bisa
 * dipanggil siapa pun yang login, dan daftar kode yang terbuka untuk umum
 * bukan lagi undangan.
 */
final class CheckWorkerInviteAvailabilityAction
{
    public function handle(string $city, string $province): bool
    {
        return WorkerInviteCode::query()->usable()->forArea($city, $province)->exists();
    }
}
