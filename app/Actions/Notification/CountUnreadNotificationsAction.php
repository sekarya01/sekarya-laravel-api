<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\User;
use App\Models\UserNotification;

/**
 * Angka badge lonceng. COUNT di server — menghitung halaman cursor yang
 * sudah dimuat di klien pasti salah begitu ada halaman kedua.
 */
final class CountUnreadNotificationsAction
{
    public function handle(User $user): int
    {
        return UserNotification::query()
            ->where('user_id', $user->getKey())
            ->unread()
            ->count();
    }
}
