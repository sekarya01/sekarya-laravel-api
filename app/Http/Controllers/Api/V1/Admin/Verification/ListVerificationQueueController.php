<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Verification;

use App\Actions\Admin\Verification\ListVerificationQueueAction;
use App\Data\Admin\VerificationQueueData;
use App\Http\Requests\Api\V1\Admin\VerificationQueueRequest;
use App\Http\Resources\Api\V1\Admin\AdminVerificationResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListVerificationQueueController
{
    public function __construct(private readonly ListVerificationQueueAction $action) {}

    public function __invoke(VerificationQueueRequest $request): AnonymousResourceCollection
    {
        return AdminVerificationResource::collection(
            $this->action->handle(VerificationQueueData::fromRequest($request)),
        );
    }
}
