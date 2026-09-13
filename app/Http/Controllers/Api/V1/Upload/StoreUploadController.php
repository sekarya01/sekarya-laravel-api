<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Upload;

use App\Http\Requests\Api\V1\Upload\StoreUploadRequest;
use App\Http\Resources\Api\V1\UploadResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class StoreUploadController
{
    public function __invoke(StoreUploadRequest $request): JsonResponse
    {
        $folder = $request->string('purpose', 'task')->value() === 'avatar'
            ? 'uploads/avatars'
            : 'uploads/tasks';

        // Nama berkas selalu acak (hashName): nama asli dari perangkat tidak
        // pernah menyentuh disk — "KTP_Budi.jpg" tidak boleh bocor lewat URL.
        $path = $request->file('file')->store($folder, 'public');

        return UploadResource::make([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ])->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
