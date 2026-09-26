<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notification;

use App\Actions\Notification\MarkNotificationReadAction;
use App\Http\Resources\Api\V1\UserNotificationResource;
use Illuminate\Http\Request;

final class MarkNotificationReadController
{
    public function __construct(private readonly MarkNotificationReadAction $action) {}

    public function __invoke(Request $request, string $notification): UserNotificationResource
    {
        return UserNotificationResource::make($this->action->handle($notification, $request->user()));
    }
}
