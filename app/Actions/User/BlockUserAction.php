<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Exceptions\Domain\CannotBlockSelfException;
use App\Models\User;
use App\Models\UserBlock;

/**
 * Blokir pengguna (G7) — idempoten. Efeknya: feed & profil saling
 * tersembunyi, dan penawaran di antara keduanya ditolak.
 */
final class BlockUserAction
{
    public function handle(User $blocker, User $blocked): void
    {
        if ($blocker->is($blocked)) {
            throw CannotBlockSelfException::make();
        }

        UserBlock::query()->firstOrCreate([
            'blocker_id' => $blocker->getKey(),
            'blocked_id' => $blocked->getKey(),
        ]);
    }
}
