<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\WalletWithdrawal;
use Illuminate\Http\Request;

/**
 * Permintaan penarikan milik sendiri.
 *
 * Rekening tujuan keluar sebatas BANK dan NAMA PEMILIK. Nomor rekening tidak
 * ada di tabel ini sama sekali (ia terenkripsi di baris verifikasi), jadi
 * tidak ada yang bisa bocor dari sini bahkan kalau seseorang menambahkan
 * `'...' => $this->verification->...` tanpa berpikir — kolomnya `$hidden` dan
 * terenkripsi di modelnya.
 *
 * @mixin WalletWithdrawal
 */
final class WalletWithdrawalResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'amount' => (int) $this->amount,
            'status' => $this->status->value,
            'awaits_processing' => $this->status->awaitsProcessing(),
            'destination' => $this->whenLoaded('verification', fn (): array => [
                'bank_code' => $this->verification->bank_code,
                'account_holder_name' => $this->verification->account_holder_name,
            ]),
            'rejection_reason' => $this->rejection_reason,
            'transfer_reference' => $this->transfer_reference,
            'completed_at' => $this->iso($this->completed_at),
            'rejected_at' => $this->iso($this->rejected_at),
            'cancelled_at' => $this->iso($this->cancelled_at),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
