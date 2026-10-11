<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Upload;

use App\Actions\Upload\StoreUploadAction;
use App\Http\Requests\Api\V1\Upload\StoreUploadRequest;
use App\Http\Resources\Api\V1\UploadResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

final class StoreUploadController
{
    public function __construct(private readonly StoreUploadAction $action) {}

    public function __invoke(StoreUploadRequest $request): JsonResponse
    {
        $path = $this->action->handle(
            $request->file('file'),
            $request->string('purpose', 'task')->value(),
            $request->user(),
        );

        return UploadResource::make([
            'path' => $path,
            'url' => Storage::disk('public')->url($path),
        ])->response()->setStatusCode(Response::HTTP_CREATED);
    }
}
