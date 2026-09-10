<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Access;

use App\Actions\Admin\Access\CreateAdminAction;
use App\Data\Admin\CreateAdminData;
use App\Http\Requests\Api\V1\Admin\CreateAdminRequest;
use App\Http\Resources\Api\V1\Admin\AdminResource;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class CreateAdminController
{
    public function __construct(private readonly CreateAdminAction $action) {}

    public function __invoke(CreateAdminRequest $request): JsonResponse
    {
        $admin = $this->action->handle(
            CreateAdminData::fromRequest($request),
            $request->user(),
        );

        return AdminResource::make($admin)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
