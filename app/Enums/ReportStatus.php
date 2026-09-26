<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    case Reviewed = 'reviewed';

    public function isOpen(): bool
    {
        return $this === self::Open;
    }
}
