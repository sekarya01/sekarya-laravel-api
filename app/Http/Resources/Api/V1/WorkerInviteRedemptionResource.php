<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\WorkerInviteCode;
use Illuminate\Http\Request;

/**
 * Hasil redeem: bukan profil pekerjanya, melainkan keadaan KODENYA.
 *
 * Klien butuh dua angka ini untuk kalimat konfirmasi ("kode masih bisa
 * dipakai 3x lagi" / "ini pemakaian terakhir") — profil pekerjanya sendiri
 * dibaca ulang lewat GET /me seperti biasa.
 *
 * @mixin WorkerInviteCode
 */
final class WorkerInviteRedemptionResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'remaining_uses' => $this->resource->remainingUses(),
            'expires_at' => $this->iso($this->resource->expires_at),
            'is_last_use' => $this->resource->remainingUses() === 0,
        ];
    }
}
