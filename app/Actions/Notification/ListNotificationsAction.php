<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Data\Notification\ListNotificationsData;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Kotak masuk notifikasi milik sendiri, terbaru dulu.
 *
 * Selalu dilingkupi `user_id` pemanggil — tidak ada parameter yang bisa
 * menunjuk kotak masuk orang lain.
 */
final class ListNotificationsAction
{
    /** @return CursorPaginator<int, UserNotification> */
    public function handle(ListNotificationsData $data, User $user): CursorPaginator
    {
        return UserNotification::query()
            ->where('user_id', $user->getKey())
            ->when($data->unreadOnly, fn ($q) => $q->unread())
            ->latestFirst()
            ->cursorPaginate($data->page->perPage);
    }
}
