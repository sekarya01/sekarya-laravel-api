<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Resources\Api\V1\WorkerProfileResource;
use Illuminate\Http\Request;

final class ShowWorkerProfileController
{
    public function __invoke(Request $request): WorkerProfileResource
    {
        // `workerProfileOrNew()`, bukan `workerProfileOrCreate()`: membaca
        // profil sendiri tidak boleh menulis baris. Yang belum pernah mengisi
        // apa pun tetap mendapat profil utuh — seluruhnya warisan dari akun,
        // dengan `configured: false` sebagai penandanya.
        return WorkerProfileResource::make($request->user()->workerProfileOrNew());
    }
}
