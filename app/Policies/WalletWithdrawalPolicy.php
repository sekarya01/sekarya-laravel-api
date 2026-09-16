<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WalletWithdrawal;

/** Permintaan penarikan hanya bisa dibatalkan pemiliknya. */
final class WalletWithdrawalPolicy
{
    public function cancel(User $user, WalletWithdrawal $withdrawal): bool
    {
        return $user->getKey() === $withdrawal->user_id;
    }
}
