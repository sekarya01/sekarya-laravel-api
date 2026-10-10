<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Chat;

use App\Actions\Chat\CountUnreadChatMessagesAction;
use App\Http\Resources\Api\V1\NotificationCountResource;
use Illuminate\Http\Request;

/** Badge ikon chat — `{data:{count}}`. */
final class ShowUnreadChatCountController
{
    public function __construct(private readonly CountUnreadChatMessagesAction $action) {}

    public function __invoke(Request $request): NotificationCountResource
    {
        return NotificationCountResource::make(['count' => $this->action->handle($request->user())]);
    }
}
