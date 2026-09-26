<?php

declare(strict_types=1);

namespace App\Enums;

enum ReportReason: string
{
    case Spam = 'spam';
    case Fraud = 'fraud';
    case Abuse = 'abuse';
    case Inappropriate = 'inappropriate';
    case Other = 'other';
}
