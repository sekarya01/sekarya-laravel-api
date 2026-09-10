<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Access;

use App\Actions\Admin\Access\ListAdminsAction;
use App\Data\CursorPageData;
use App\Http\Resources\Api\V1\Admin\AdminResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListAdminsController
{
    public function __construct(private readonly ListAdminsAction $action) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        return AdminResource::collection(
            $this->action->handle(CursorPageData::fromRequest($request)),
        );
    }
}
