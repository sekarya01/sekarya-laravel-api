<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Bentuk room chat. Satu room per task. `group` bila task-nya berkuota > 1
 * (keputusan produk 2026-10-04: task beberapa pekerja = chat bersama sejak
 * pekerja pertama, walau perekrutan ditutup dengan satu orang) ATAU bila
 * pekerja yang diterima > 1; selain itu `individual`.
 */
enum ChatRoomType: string
{
    case Individual = 'individual';
    case Group = 'group';

    public static function forTask(int $workersNeeded, int $acceptedWorkers): self
    {
        return $workersNeeded > 1 || $acceptedWorkers > 1 ? self::Group : self::Individual;
    }
}
