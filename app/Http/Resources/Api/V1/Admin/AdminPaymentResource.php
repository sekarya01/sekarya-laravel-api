<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Admin;

use App\Http\Resources\Api\V1\BaseResource;
use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * Satu tagihan di antrean konfirmasi transfer.
 *
 * `gateway_payload` tidak ada di tabel ini dan tidak boleh ditambahkan ke
 * sini kalau nanti ada — batas pengungkapan itu diuji.
 *
 * @mixin Payment
 */
final class AdminPaymentResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'status' => $this->status->value,
            'amount' => $this->amount,
            'is_held' => $this->status->opensActivity(),
            'awaits_confirmation' => $this->status->awaitsConfirmation(),

            // Kapan pemberi kerja MENGAKU transfer — bukan kapan barisnya
            // dibuat. Baris tagihan sudah ada sejak pelamar pertama diterima.
            'reported_at' => $this->iso($this->reported_at),
            'rejection_reason' => $this->rejection_reason,
            'paid_at' => $this->iso($this->paid_at),
            'held_at' => $this->iso($this->held_at),
            'released_at' => $this->iso($this->released_at),

            'task' => $this->whenLoaded('task', fn (): array => [
                'id' => $this->task->ulid,
                'task_number' => $this->task->task_number,
                'title' => $this->task->title,
                'status' => $this->task->status->value,
                'workers_hired' => $this->task->workers_hired,
                // TOTAL seluruh pekerja. Angka inilah yang harus sama dengan
                // jumlah yang masuk rekening; harga per orang ada di bids.
                'agreed_amount' => $this->task->agreed_amount,
            ]),
            'payer' => AdminUserResource::make($this->whenLoaded('payer')),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
