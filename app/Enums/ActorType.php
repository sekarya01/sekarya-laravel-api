<?php

declare(strict_types=1);

namespace App\Enums;

enum ActorType: string
{
    case Poster = 'poster';
    case Worker = 'worker';
    case System = 'system';
    case Admin = 'admin';
}
