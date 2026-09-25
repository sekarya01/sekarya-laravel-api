<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notification;

use App\Actions\Notification\CountUnreadNotificationsAction;
use App\Http\Resources\Api\V1\NotificationCountResource;
use Illuminate\Http\Request;

final class ShowUnreadNotificationCountController
{
    public function __construct(private readonly CountUnreadNotificationsAction $action) {}

    public function __invoke(Request $request): NotificationCountResource
    {
        return NotificationCountResource::make(['count' => $this->action->handle($request->user())]);
    }
}
