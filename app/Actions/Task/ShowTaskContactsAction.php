<?php

declare(strict_types=1);

namespace App\Actions\Task;

use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Task;

/**
 * Nomor kontak peserta task yang sudah deal (B17).
 *
 * Nomor HP sengaja tidak pernah keluar di profil publik. Ia hanya berguna
 * setelah kedua pihak sepakat bekerja, dan hanya di antara mereka — karena itu
 * endpoint ini menuntut status yang sudah deal, dan Policy menuntut pemanggil
 * adalah peserta task.
 */
final class ShowTaskContactsAction
{
    /** Status yang berarti "sudah deal, pekerjaan berjalan". */
    private const array OPEN = [
        TaskStatus::Dealt->value,
        TaskStatus::Active->value,
        TaskStatus::Submitted->value,
    ];

    /**
     * @return list<array{id: string, name: ?string, role: string, phone: ?string}>
     */
    public function handle(Task $task): array
    {
        if (! in_array($task->status->value, self::OPEN, true)) {
            throw InvalidStatusTransitionException::between($task->status->value, TaskStatus::Active->value);
        }

        $poster = $task->poster;

        $contacts = [[
            'id' => $poster->ulid,
            'name' => $poster->name,
            'role' => 'poster',
            'phone' => $poster->phone,
        ]];

        foreach ($task->workers()->get() as $worker) {
            $contacts[] = [
                'id' => $worker->ulid,
                'name' => $worker->workerProfileOrNew()->resolvedName(),
                'role' => 'worker',
                'phone' => $worker->workerProfileOrNew()->resolvedPhone(),
            ];
        }

        return $contacts;
    }
}
