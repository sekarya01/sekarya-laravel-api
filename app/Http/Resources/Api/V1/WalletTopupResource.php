<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\WalletTopup;
use Illuminate\Http\Request;

/** @mixin WalletTopup */
final class WalletTopupResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'amount' => (int) $this->amount,
            'status' => $this->status->value,
            // Kunci yang menggerakkan UI: bolanya masih di tangan pengguna
            // atau sudah di tangan pengelola.
            'awaits_confirmation' => $this->status->awaitsConfirmation(),
            'sender_note' => $this->sender_note,
            // Alasan penolakan. Tanpa ini "ditolak" tidak mengatakan apa pun
            // tentang apa yang harus diperbaiki, dan permintaan berikutnya
            // akan sama saja.
            'rejection_reason' => $this->rejection_reason,
            'confirmed_at' => $this->iso($this->confirmed_at),
            'rejected_at' => $this->iso($this->rejected_at),
            'cancelled_at' => $this->iso($this->cancelled_at),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
