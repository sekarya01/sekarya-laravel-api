<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notification;

use App\Actions\Notification\CountUnreadNotificationsAction;
use App\Actions\Notification\MarkAllNotificationsReadAction;
use App\Http\Resources\Api\V1\NotificationCountResource;
use Illuminate\Http\Request;

final class MarkAllNotificationsReadController
{
    public function __construct(
        private readonly MarkAllNotificationsReadAction $action,
        private readonly CountUnreadNotificationsAction $count,
    ) {}

    public function __invoke(Request $request): NotificationCountResource
    {
        $user = $request->user();
        $this->action->handle($user);

        // Bentuknya sama dengan `unread-count` (`{count}`): sesudah seluruhnya
        // ditandai, sisanya nol.
        return NotificationCountResource::make(['count' => $this->count->handle($user)]);
    }
}
