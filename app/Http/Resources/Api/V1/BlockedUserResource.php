<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\UserBlock;
use Illuminate\Http\Request;

/**
 * Satu baris daftar blokir (G7): orang yang diblokir + kapan diblokir.
 *
 * @mixin UserBlock
 */
final class BlockedUserResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $this->blocked;

        return [
            'id' => $user->ulid,
            'name' => $user->name,
            'blocked_at' => $this->iso($this->created_at),
        ];
    }
}
