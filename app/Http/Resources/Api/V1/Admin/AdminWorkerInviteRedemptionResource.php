<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Http\Resources\Api\V1\BaseResource;
use App\Models\WorkerInviteRedemption;
use Illuminate\Http\Request;

/**
 * Satu jejak redeem: siapa, kapan, dan apakah identitasnya sudah terverifikasi.
 *
 * `identity_verified` dihitung, bukan disimpan — status verifikasi bisa
 * dicabut, dan boolean yang tertinggal akan bohong (aturan yang sama dengan
 * `User::isIdentityVerified()`).
 *
 * @mixin WorkerInviteRedemption
 */
final class AdminWorkerInviteRedemptionResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $user = $this->resource->user;

        $verified = $user?->verifications
            ?->first(fn ($v) => $v->type === VerificationType::Identity
                && $v->status === VerificationStatus::Verified) !== null;

        return [
            'user_id' => $user?->getKey(),
            'name' => $user?->name,
            'email' => $user?->email,
            'phone' => $user?->phone,
            'identity_verified' => $verified ?? false,
            'redeemed_at' => $this->iso($this->resource->created_at),
        ];
    }
}
