<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/** Hasil unggahan satu berkas: path untuk `photos[]`, url untuk pratinjau. */
final class UploadResource extends BaseResource
{
    /** @return array<string, string> */
    public function toArray(Request $request): array
    {
        return [
            'path' => (string) $this->resource['path'],
            'url' => (string) $this->resource['url'],
        ];
    }
}
