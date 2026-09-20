<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\WorkerInvite;

use App\Actions\Admin\WorkerInvite\CreateWorkerInviteCodeAction;
use App\Http\Requests\Api\V1\Admin\WorkerInvite\StoreWorkerInviteCodeRequest;
use App\Http\Resources\Api\V1\Admin\AdminWorkerInviteCodeResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

final class CreateWorkerInviteCodeController
{
    public function __construct(private readonly CreateWorkerInviteCodeAction $action) {}

    public function __invoke(StoreWorkerInviteCodeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $result = $this->action->handle(
            (int) ($data['max_uses'] ?? 1),
            isset($data['expires_at']) && $data['expires_at'] !== null
                ? Carbon::parse($data['expires_at'])
                : null,
            $data['note'] ?? null,
            $request->user(),
        );

        // Plain hanya keluar di sini — responsnya memakai envelope yang sama
        // (`data` + `message`) supaya klien mobile tidak perlu cabang khusus.
        return response()->json([
            'data' => [
                ...AdminWorkerInviteCodeResource::make($result['code'])->toArray($request),
                'plain_code' => $result['plain'],
            ],
            'message' => 'Kode undangan mitra dibuat. Catat sekarang — kode aslinya tidak disimpan.',
        ], 201);
    }
}
