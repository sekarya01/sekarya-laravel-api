<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;

/**
 * Satu kontak pada task yang sudah deal (B17): pemberi kerja atau pekerja.
 * Hanya keluar lewat `GET tasks/{task}/contacts`, hanya untuk peserta task
 * yang sudah deal.
 */
final class TaskContactResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array{id: string, name: ?string, role: string, phone: ?string} $contact */
        $contact = $this->resource;

        return [
            'id' => $contact['id'],
            'name' => $contact['name'],
            'role' => $contact['role'],
            'phone' => $contact['phone'],
        ];
    }
}
