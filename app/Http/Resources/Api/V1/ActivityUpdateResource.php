<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\ActivityUpdate;
use Illuminate\Http\Request;

/** Satu catatan kemajuan pekerja (B9). @mixin ActivityUpdate */
final class ActivityUpdateResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'note' => $this->note,
            'photo' => $this->photo,
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
