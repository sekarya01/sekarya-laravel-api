<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\BaseResource;
use App\Models\WalletWithdrawal;
use Illuminate\Http\Request;

/**
 * Satu permintaan pencairan di antrean pengelola.
 *
 * NOMOR REKENING TIDAK ADA DI SINI, dan itu bukan kelalaian yang perlu
 * "dilengkapi". Ia terbaca di `GET /admin/verifications/{verification}`, satu
 * layar lebih jauh, dan pembacaannya dicatat sebagai `verification.viewed` —
 * pola yang sama dengan antrean verifikasi, yang daftarnya secara harfiah
 * tidak punya kode untuk mengeluarkan NIK. Yang keluar di sini cukup untuk
 * menyaring antrean: bank mana, atas nama siapa, dan `verification_id` untuk
 * membukanya.
 *
 * @mixin WalletWithdrawal
 */
final class AdminWalletWithdrawalResource extends BaseResource
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
                // Id yang dipakai membuka
                // `GET /admin/verifications/{verification}`. Tabel verifikasi
                // memakai id biasa, bukan ULID — rute pengelola tidak terbuka
                // untuk publik dan seluruh Resource verifikasi sudah
                // mengeluarkan `id` apa adanya.
                'verification_id' => $this->verification->id,
                'bank_code' => $this->verification->bank_code,
                'account_holder_name' => $this->verification->account_holder_name,
                // Cukup untuk mencocokkan dengan mutasi; nomor utuh tetap
                // hanya di detail verifikasi, yang pembacaannya dicatat.
                'account_number_masked' => $this->verification->accountNumberMasked(),
            ]),
            'rejection_reason' => $this->rejection_reason,
            'transfer_reference' => $this->transfer_reference,
            'user' => AdminUserResource::make($this->whenLoaded('user')),
            'completed_at' => $this->iso($this->completed_at),
            'rejected_at' => $this->iso($this->rejected_at),
            'cancelled_at' => $this->iso($this->cancelled_at),
            'processed_at' => $this->iso($this->processed_at),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
