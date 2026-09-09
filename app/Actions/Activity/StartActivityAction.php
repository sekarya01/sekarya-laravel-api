<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use App\Enums\ActivityStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Exceptions\Domain\PaymentNotHeldException;
use App\Models\Activity;
use Illuminate\Database\ConnectionInterface;

final class StartActivityAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(Activity $activity): Activity
    {
        return $this->db->transaction(function () use ($activity): Activity {
            // Pemeriksaan ulang meski activity sudah ada: dana bisa sudah
            // dikembalikan sejak activity dibuka.
            if (! $activity->payment->status->opensActivity()) {
                throw PaymentNotHeldException::becauseStatus($activity->payment->status);
            }

            if (! $activity->status->canTransitionTo(ActivityStatus::InProgress)) {
                throw InvalidStatusTransitionException::between(
                    $activity->status->value,
                    ActivityStatus::InProgress->value,
                );
            }

            $activity->forceFill([
                'status' => ActivityStatus::InProgress,
                'started_at' => now(),
            ])->save();

            return $activity;
        });
    }
}
