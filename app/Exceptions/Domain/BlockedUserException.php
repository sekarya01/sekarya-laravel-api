<?php

declare(strict_types=1);

namespace App\Exceptions\Domain;

use App\Models\User;

/**
 * Penawaran ditolak karena ada blokir antara penawar dan pemberi kerja (G7).
 */
final class BlockedUserException extends DomainException
{
    public static function make(User $blocked): self
    {
        return new self(
            $blocked->name.' tidak bisa dijangkau dari akun ini.',
        );
    }

    public function errorCode(): string
    {
        return 'user_blocked';
    }
}
