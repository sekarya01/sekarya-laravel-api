<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notification;

use App\Actions\Notification\MarkAllNotificationsReadAction;
use App\Http\Resources\Api\V1\NotificationCountResource;
use Illuminate\Http\Request;

final class MarkAllNotificationsReadController
{
    public function __construct(private readonly MarkAllNotificationsReadAction $action) {}

    public function __invoke(Request $request): NotificationCountResource
    {
        return NotificationCountResource::make(['marked' => $this->action->handle($request->user())]);
    }
}
