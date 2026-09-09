<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payment;

use App\Actions\Payment\HoldPaymentAction;
use App\Http\Resources\Api\V1\ActivityResource;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * STUB. Menandai dana sudah ditahan, lalu MEMBUKA activity.
 *
 * Nanti pemicunya webhook gateway, bukan endpoint ini. Yang tidak berubah:
 * activity hanya boleh terbuka ketika dana benar-benar ditahan.
 *
 * Mengembalikan DAFTAR activity — satu per pekerja yang diterima. Task satu
 * orang tetap memakai bentuk yang sama, berisi satu elemen, supaya klien
 * tidak perlu dua jalur pembacaan untuk hal yang sama.
 */
final class HoldPaymentController
{
    public function __construct(private readonly HoldPaymentAction $action) {}

    public function __invoke(Request $request, Task $task): JsonResponse
    {
        $activities = $this->action->handle($task, $request->user());

        return ActivityResource::collection(
            $activities->load(['task.category', 'worker', 'payment']),
        )->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
