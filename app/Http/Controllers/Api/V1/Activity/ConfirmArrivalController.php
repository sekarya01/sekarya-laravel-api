<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Activity;

use App\Actions\Activity\ConfirmArrivalAction;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Activity;

/** Pemberi kerja mengonfirmasi pekerjanya sudah sampai di lokasi. */
final class ConfirmArrivalController
{
    public function __construct(private readonly ConfirmArrivalAction $action) {}

    public function __invoke(Activity $activity): ActivityResource
    {
        return ActivityResource::make($this->action->handle($activity)->load('payment'));
    }
}
