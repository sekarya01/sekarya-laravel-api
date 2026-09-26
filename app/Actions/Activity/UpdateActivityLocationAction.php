<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use App\Enums\ActivityStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Activity;

/**
 * Simpan lokasi langsung pekerja dalam perjalanan (B8).
 *
 * Hanya bermakna selama `on_the_way`: sesudah tiba, "ETA" berhenti menjadi
 * pertanyaan. Koordinat ini adalah yang sedang DIBAGIKAN pekerja (bukan lokasi
 * kerja tersimpannya), jadi dipakai apa adanya untuk menghitung jarak.
 */
final class UpdateActivityLocationAction
{
    public function handle(Activity $activity, float $latitude, float $longitude): Activity
    {
        if ($activity->status !== ActivityStatus::OnTheWay) {
            throw InvalidStatusTransitionException::between(
                $activity->status->value,
                ActivityStatus::OnTheWay->value,
            );
        }

        $activity->forceFill([
            'live_latitude' => $latitude,
            'live_longitude' => $longitude,
            'live_updated_at' => now(),
        ])->save();

        return $activity;
    }
}
