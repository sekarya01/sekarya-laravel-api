<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Upload;

use App\Http\Requests\Api\V1\Upload\StoreUploadRequest;
use App\Http\Resources\Api\V1\UploadResource;
use App\Support\ProofPhotos;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class StoreUploadController
{
    public function __construct(private readonly ProofPhotos $proofs) {}

    public function __invoke(StoreUploadRequest $request): JsonResponse
    {
        $file = $request->file('file');

        // Nama berkas selalu acak (hashName): nama asli dari perangkat tidak
        // pernah menyentuh disk — "KTP_Budi.jpg" tidak boleh bocor lewat URL.
        // Foto bukti kerja menambah tanda pemilik di depannya, supaya
        // penyerahan hasil bisa menolak foto milik orang lain (ProofPhotos).
        $path = match ($request->string('purpose', 'task')->value()) {
            'avatar' => $file->store('uploads/avatars', 'public'),
            'proof' => $this->proofs->store($file, $request->user()),
            default => $file->store('uploads/tasks', 'public'),
        };

        return UploadResource::make([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ])->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
