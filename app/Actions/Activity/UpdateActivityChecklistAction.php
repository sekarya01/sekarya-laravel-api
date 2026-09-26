<?php

declare(strict_types=1);

namespace App\Actions\Activity;

use App\Exceptions\Domain\ChecklistStateMismatchException;
use App\Models\Activity;

/**
 * Simpan centang checklist pekerja (B10).
 *
 * Larik `state` harus sepanjang `tasks.checklist`; panjang yang berbeda
 * ditolak, bukan dipotong diam-diam.
 */
final class UpdateActivityChecklistAction
{
    /**
     * @param  list<bool>  $state
     */
    public function handle(Activity $activity, array $state): Activity
    {
        $expected = count($activity->task->checklist ?? []);

        if (count($state) !== $expected) {
            throw ChecklistStateMismatchException::forCount($expected, count($state));
        }

        $activity->forceFill([
            'checklist_state' => array_values(array_map(static fn (mixed $v): bool => (bool) $v, $state)),
        ])->save();

        return $activity;
    }
}
