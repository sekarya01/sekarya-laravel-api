<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Models\Payment;
use Illuminate\Http\Request;

/**
 * STUB — hanya status uang. Rincian gateway belum ada dan memang belum perlu.
 *
 * @mixin Payment
 */
final class PaymentResource extends BaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->ulid,
            'status' => $this->status->value,
            'amount' => $this->amount,
            // Kunci yang menggerakkan UI: dana sudah ditahan atau belum.
            'is_held' => $this->status->opensActivity(),

            // Laporan transfer menunggu pengelola. Pemberi kerja yang melihat
            // ini tahu bahwa bolanya bukan lagi di tangannya.
            'awaits_confirmation' => $this->status->awaitsConfirmation(),
            'reported_at' => $this->iso($this->reported_at),

            // Alasan laporan transfer sebelumnya ditolak. Tanpa ini, "kembali
            // ke pending" tidak mengatakan apa pun tentang apa yang harus
            // diperbaiki, dan laporan berikutnya akan sama saja.
            'rejection_reason' => $this->rejection_reason,

            'paid_at' => $this->iso($this->paid_at),
            'held_at' => $this->iso($this->held_at),
            'released_at' => $this->iso($this->released_at),
            'refunded_at' => $this->iso($this->refunded_at),
            'cancelled_at' => $this->iso($this->cancelled_at),
            'created_at' => $this->iso($this->created_at),
        ];
    }
}
