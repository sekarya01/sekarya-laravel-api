<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Requests\Api\V1\User\StoreVerificationDocumentRequest;
use App\Support\VerificationDocuments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Unggah satu dokumen identitas (G2a). Balasannya HANYA `path` — tidak ada
 * `url`, karena berkasnya tidak pernah bisa diakses publik. Path itu lalu
 * dipakai di `POST me/verifications` sebagai `id_card_photo_path`/`selfie_photo_path`.
 */
final class StoreVerificationDocumentController
{
    public function __construct(private readonly VerificationDocuments $documents) {}

    public function __invoke(StoreVerificationDocumentRequest $request): JsonResponse
    {
        $path = $this->documents->store($request->file('file'), $request->user());

        return response()->json(['data' => ['path' => $path]], Response::HTTP_CREATED);
    }
}
