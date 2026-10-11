<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\WorkerInvite;

use App\Actions\Admin\WorkerInvite\CreateWorkerInviteCodeAction;
use App\Data\Admin\CreateWorkerInviteCodeData;
use App\Http\Requests\Api\V1\Admin\WorkerInvite\StoreWorkerInviteCodeRequest;
use App\Http\Resources\Api\V1\Admin\AdminWorkerInviteCodeResource;
use Illuminate\Http\JsonResponse;

final class CreateWorkerInviteCodeController
{
    public function __construct(private readonly CreateWorkerInviteCodeAction $action) {}

    public function __invoke(StoreWorkerInviteCodeRequest $request): JsonResponse
    {
        $result = $this->action->handle(
            CreateWorkerInviteCodeData::fromValues($request->validated(), $request->ip()),
            $request->user(),
        );

        $result['code']->loadCount('redemptions')->load('creator');

        return AdminWorkerInviteCodeResource::created($result['code'], $result['plain'])
            ->additional(['message' => 'Kode undangan mitra dibuat. Kodenya tampil terus di menu Kode Mitra.'])
            ->response()
            ->setStatusCode(201);
    }
}
