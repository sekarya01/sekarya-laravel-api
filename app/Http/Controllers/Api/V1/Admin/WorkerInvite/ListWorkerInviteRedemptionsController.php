<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\WorkerInvite;

use App\Http\Resources\Api\V1\Admin\AdminWorkerInviteRedemptionResource;
use App\Models\WorkerInviteCode;
use App\Models\WorkerInviteRedemption;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListWorkerInviteRedemptionsController
{
    /**
     * Siapa saja yang memakai kode ini — dasar meja verifikasi: pengelola
     * melihat pekerja yang masuk lewat kode, lalu memverifikasi identitasnya
     * di antrean verifikasi seperti biasa.
     */
    public function __invoke(WorkerInviteCode $code): AnonymousResourceCollection
    {
        $redemptions = WorkerInviteRedemption::query()
            ->where('invite_code_id', $code->getKey())
            ->with(['user.verifications'])
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return AdminWorkerInviteRedemptionResource::collection($redemptions);
    }
}
