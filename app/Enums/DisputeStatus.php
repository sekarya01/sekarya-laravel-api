<?php

declare(strict_types=1);

namespace App\Enums;

enum DisputeStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
