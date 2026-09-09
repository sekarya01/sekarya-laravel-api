<?php

declare(strict_types=1);

namespace App\Enums;

enum ReviewerRole: string
{
    case Poster = 'poster';
    case Worker = 'worker';

    /** Penilaian dari poster menaikkan agregat worker, dan sebaliknya. */
    public function affectedAggregate(): string
    {
        return match ($this) {
            self::Poster => 'worker',
            self::Worker => 'poster',
        };
    }
}
