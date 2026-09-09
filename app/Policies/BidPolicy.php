<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Bid;
use App\Models\User;

final class BidPolicy
{
    /** Hanya penawarnya sendiri yang boleh menarik penawaran. */
    public function withdraw(User $user, Bid $bid): bool
    {
        return $user->getKey() === $bid->bidder_id;
    }

    /** Hanya pemberi kerja pemilik task yang boleh menerima penawaran. */
    public function accept(User $user, Bid $bid): bool
    {
        return $user->getKey() === $bid->task->poster_id;
    }
}
