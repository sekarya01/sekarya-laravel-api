<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\BaseResource;
use App\Models\WalletTopup;
use Illuminate\Http\Request;

/**
 * Satu permintaan isi saldo di antrean pengelola.
 *
 * `sender_note` ikut karena itulah satu-satunya petunjuk untuk menemukan
 * transfernya di mutasi rekening — antrean tanpa itu hanya bisa diputuskan
 * dengan menebak.
 *
 * @mixin WalletTopup
 */
final class AdminWalletTopupResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'amount' => (int) $this->amount,
            'status' => $this->status->value,
            'awaits_confirmation' => $this->status->awaitsConfirmation(),
            'sender_note' => $this->sender_note,
            'rejection_reason' => $this->rejection_reason,
            'user' => AdminUserResource::make($this->whenLoaded('user')),
            'confirmed_at' => $this->iso($this->confirmed_at),
            'rejected_at' => $this->iso($this->rejected_at),
            'cancelled_at' => $this->iso($this->cancelled_at),
            'reviewed_at' => $this->iso($this->reviewed_at),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
