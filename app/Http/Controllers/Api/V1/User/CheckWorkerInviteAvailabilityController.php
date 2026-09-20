<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Requests\Api\V1\User\CheckWorkerInviteAvailabilityRequest;
use App\Models\WorkerInviteCode;
use Illuminate\Http\JsonResponse;

final class CheckWorkerInviteAvailabilityController
{
    /**
     * Sinyal ketersediaan saja — boolean, tanpa isi kode.
     *
     * Isi kodenya tidak boleh keluar di sini: endpoint ini bisa dipanggil
     * siapa pun yang login, dan daftar kode yang terbuka untuk umum bukan
     * lagi undangan. Aplikasi memakai jawabannya untuk hint ("pendaftaran
     * mitra dibuka di kotamu — minta kodenya ke pengelola"), distribusi
     * kodenya sendiri tetap di luar aplikasi.
     */
    public function __invoke(CheckWorkerInviteAvailabilityRequest $request): JsonResponse
    {
        $data = $request->validated();

        $available = WorkerInviteCode::query()
            ->usable()
            ->forArea($data['city'], $data['province'])
            ->exists();

        return response()->json(['data' => ['available' => $available]]);
    }
}
