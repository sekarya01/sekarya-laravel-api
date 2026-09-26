<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Notification;

use App\Actions\Notification\ListNotificationsAction;
use App\Data\Notification\ListNotificationsData;
use App\Http\Requests\Api\V1\Notification\ListNotificationsRequest;
use App\Http\Resources\Api\V1\UserNotificationResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListNotificationsController
{
    public function __construct(private readonly ListNotificationsAction $action) {}

    public function __invoke(ListNotificationsRequest $request): AnonymousResourceCollection
    {
        return UserNotificationResource::collection(
            $this->action->handle(ListNotificationsData::fromRequest($request), $request->user()),
        );
    }
}
