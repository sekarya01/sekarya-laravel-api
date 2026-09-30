<?php

declare(strict_types=1);

namespace App\Enums;

/** Peran peserta PADA TASK room itu: pemberi kerja (`user`) atau mitra (`worker`). */
enum ChatParticipantType: string
{
    case User = 'user';
    case Worker = 'worker';

    /** Pemberi kerja pemilik room; mitra anggota. */
    public function role(): string
    {
        return $this === self::User ? 'owner' : 'member';
    }
}
