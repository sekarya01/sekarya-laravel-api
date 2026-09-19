<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use App\Enums\ActivityStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Exceptions\Domain\PaymentNotHeldException;
use App\Models\Activity;
use Illuminate\Database\ConnectionInterface;

/**
 * Pekerja berangkat ke lokasi.
 *
 * Langkah pertama yang benar-benar ia lakukan sesudah diterima — dan yang
 * membuat pemberi kerja tahu ada orang dalam perjalanan, bukan menebak dari
 * diamnya layar.
 *
 * Dijaga dana yang sama dengan mulai bekerja: berangkat adalah waktu dan
 * ongkos yang sudah dikeluarkan pekerja, jadi ia tidak boleh diminta
 * bergerak atas tagihan yang belum dipastikan.
 */
final class DepartActivityAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(Activity $activity): Activity
    {
        return $this->db->transaction(function () use ($activity): Activity {
            if ((bool) config('sekarya.payments.gate_enabled')
                && ! $activity->payment->status->opensActivity()) {
                throw PaymentNotHeldException::becauseStatus($activity->payment->status);
            }

            if (! $activity->status->canTransitionTo(ActivityStatus::OnTheWay)) {
                throw InvalidStatusTransitionException::between(
                    $activity->status->value,
                    ActivityStatus::OnTheWay->value,
                );
            }

            $activity->forceFill([
                'status' => ActivityStatus::OnTheWay,
                'departed_at' => now(),
            ])->save();

            return $activity;
        });
    }
}
