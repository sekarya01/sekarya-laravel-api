<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\User;
use App\Models\UserNotification;

/**
 * Tandai seluruh kotak masuk sendiri sudah dibaca. Mengembalikan berapa baris
 * yang benar-benar berubah; memanggilnya dua kali aman (kedua kalinya 0).
 */
final class MarkAllNotificationsReadAction
{
    public function handle(User $user): int
    {
        return UserNotification::query()
            ->where('user_id', $user->getKey())
            ->unread()
            ->update(['read_at' => now()]);
    }
}
