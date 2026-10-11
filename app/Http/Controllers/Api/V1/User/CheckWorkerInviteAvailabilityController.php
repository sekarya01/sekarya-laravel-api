<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\CheckWorkerInviteAvailabilityAction;
use App\Http\Requests\Api\V1\User\CheckWorkerInviteAvailabilityRequest;
use Illuminate\Http\JsonResponse;

final class CheckWorkerInviteAvailabilityController
{
    public function __construct(private readonly CheckWorkerInviteAvailabilityAction $action) {}

    /**
     * Sinyal ketersediaan saja — boolean, tanpa isi kode. Aplikasi memakai
     * jawabannya untuk hint ("pendaftaran mitra dibuka di kotamu — minta
     * kodenya ke pengelola"); distribusi kodenya tetap di luar aplikasi.
     */
    public function __invoke(CheckWorkerInviteAvailabilityRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json(['data' => [
            'available' => $this->action->handle($data['city'], $data['province']),
        ]]);
    }
}
