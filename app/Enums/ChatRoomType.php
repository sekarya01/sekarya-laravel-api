<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Bentuk room chat. Satu room per task; bentuknya mengikuti jumlah pekerja
 * yang diterima saat pekerjaan dibuka — satu orang `individual`, lebih
 * `group`.
 */
enum ChatRoomType: string
{
    case Individual = 'individual';
    case Group = 'group';

    public static function forWorkerCount(int $workers): self
    {
        return $workers > 1 ? self::Group : self::Individual;
    }
}
