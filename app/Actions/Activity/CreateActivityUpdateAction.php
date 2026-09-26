<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use App\Enums\ActivityStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Activity;
use App\Models\ActivityUpdate;

/**
 * Pekerja menulis catatan kemajuan (B9): "Tiba di lokasi dan mulai angkut
 * lemari". Boleh selama pekerjaannya berjalan; sesudah hasil diserahkan,
 * kanal ini berhenti — yang berlaku adalah `worker_note` milik penyerahan.
 */
final class CreateActivityUpdateAction
{
    /** Status yang masih menerima catatan kemajuan. */
    private const array OPEN = [
        ActivityStatus::OnTheWay->value,
        ActivityStatus::Arrived->value,
        ActivityStatus::InProgress->value,
    ];

    public function handle(Activity $activity, string $note, ?string $photo = null): ActivityUpdate
    {
        if (! in_array($activity->status->value, self::OPEN, true)) {
            throw InvalidStatusTransitionException::between(
                $activity->status->value,
                ActivityStatus::InProgress->value,
            );
        }

        return $activity->updates()->create([
            'note' => $note,
            'photo' => $photo,
        ]);
    }
}
