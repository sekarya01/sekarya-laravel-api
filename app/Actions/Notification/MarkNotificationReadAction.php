<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Tandai satu notifikasi sudah dibaca.
 *
 * Dicari DI DALAM kotak masuk pemanggil, bukan lewat route model binding:
 * notifikasi milik orang lain dijawab 404 yang sama persis dengan id yang
 * tidak ada — membedakannya berarti mengonfirmasi id itu milik seseorang.
 *
 * Idempoten: yang sudah dibaca tidak diubah, `read_at` pertama dipertahankan.
 */
final class MarkNotificationReadAction
{
    /** @throws ModelNotFoundException<UserNotification> */
    public function handle(string $id, User $user): UserNotification
    {
        $notification = UserNotification::query()
            ->where('user_id', $user->getKey())
            ->whereKey($id)
            ->firstOrFail();

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $notification;
    }
}
