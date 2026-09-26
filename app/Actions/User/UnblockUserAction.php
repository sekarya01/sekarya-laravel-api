<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Exceptions\Domain\CannotBlockSelfException;
use App\Models\User;
use App\Models\UserBlock;

/** Lepas blokir pengguna (G7) — idempoten. */
final class UnblockUserAction
{
    public function handle(User $blocker, User $blocked): void
    {
        if ($blocker->is($blocked)) {
            throw CannotBlockSelfException::make();
        }

        UserBlock::query()
            ->where('blocker_id', $blocker->getKey())
            ->where('blocked_id', $blocked->getKey())
            ->delete();
    }
}
