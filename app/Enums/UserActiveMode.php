<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Mode UI yang sedang dipakai. State tampilan, BUKAN otorisasi —
 * hak atas sebuah task ditentukan oleh poster_id dan penawaran yang
 * diterima pada task itu.
 */
enum UserActiveMode: string
{
    case Hiring = 'hiring';
    case Working = 'working';
}
