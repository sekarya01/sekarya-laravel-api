<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use App\Enums\ActivityStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Activity;
use Illuminate\Database\ConnectionInterface;

/**
 * Pemberi kerja mengonfirmasi pekerjanya sudah sampai.
 *
 * Milik PEMBERI KERJA, bukan pekerja. Yang melihat orangnya berdiri di depan
 * pintu adalah tuan rumah; kalau yang datang boleh menyatakan sendiri ia
 * tiba, "sudah sampai" berhenti berarti apa pun dan tidak ada satu titik pun
 * yang bisa disanggah kalau ternyata tidak.
 *
 * Tidak ada pemeriksaan dana di sini. Yang dikerjakan aksi ini cuma mengakui
 * kejadian yang sudah terjadi di depan mata — menolaknya karena tagihan belum
 * beres tidak membuat orangnya kembali pulang.
 */
final class ConfirmArrivalAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(Activity $activity): Activity
    {
        return $this->db->transaction(function () use ($activity): Activity {
            if (! $activity->status->canTransitionTo(ActivityStatus::Arrived)) {
                throw InvalidStatusTransitionException::between(
                    $activity->status->value,
                    ActivityStatus::Arrived->value,
                );
            }

            $activity->forceFill([
                'status' => ActivityStatus::Arrived,
                'arrived_at' => now(),
            ])->save();

            return $activity;
        });
    }
}
