<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\WalletEntry;
use Illuminate\Http\Request;

/**
 * Satu baris riwayat saldo.
 *
 * `reference_type` keluar sebagai slug tabel (`wallet_topups`, `activities`,
 * `payments`) dan `reference_id` TIDAK ikut. Id internal berurutan membocorkan
 * volume bisnis — alasan yang sama membuat seluruh rute memakai ULID. Yang
 * dibutuhkan klien untuk menampilkan riwayat adalah SEBABNYA, dan itu sudah
 * ada di `type`.
 *
 * @mixin WalletEntry
 */
final class WalletEntryResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'type' => $this->type->value,
            'direction' => $this->direction->value,
            'amount' => (int) $this->amount,
            // Saldo SESUDAH baris ini. Yang membuat riwayat bisa dibaca
            // sebagai rekening koran, bukan sebagai daftar angka lepas.
            'balance_after' => (int) $this->balance_after,
            'reference_type' => $this->reference_type,
            'description' => $this->description,
            // Task yang menyebabkan baris ini — `null` untuk isi ulang,
            // penarikan, dan koreksi manual. Sebatas yang dibutuhkan baris
            // riwayat; detailnya lewat `GET /tasks/{id}` (yang punya batas
            // pengungkapannya sendiri).
            'task' => $this->whenLoaded('task', fn (): array => [
                'id' => $this->task->ulid,
                'task_number' => $this->task->task_number,
                'title' => $this->task->title,
            ]),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
