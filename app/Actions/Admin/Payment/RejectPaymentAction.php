<?php

declare(strict_types=1);

namespace App\Actions\Admin\Payment;

use App\Data\Admin\RejectPaymentData;
use App\Enums\AdminAction;
use App\Enums\PaymentStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Admin;
use App\Models\Payment;
use App\Support\AdminAuditRecorder;
use Illuminate\Database\ConnectionInterface;

/**
 * Dana tidak ditemukan → laporan ditolak → tagihan kembali ke `pending`.
 *
 * Kembali ke `pending`, bukan ke status penolakan tersendiri: pemberi kerja
 * harus bisa melapor lagi setelah memperbaiki transfernya, dan status khusus
 * "ditolak" akan menuntut jalan keluarnya sendiri untuk hal yang sudah
 * dijawab oleh `pending`. Alasannya tersimpan di baris pembayaran supaya
 * pemberi kerja bisa membacanya, dan di `admin_audit_logs` supaya keputusan
 * itu tetap ada setelah laporan berikutnya menimpanya.
 */
final class RejectPaymentAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function handle(Payment $payment, Admin $admin, RejectPaymentData $data): Payment
    {
        return $this->db->transaction(function () use ($payment, $admin, $data): Payment {
            $fresh = Payment::query()
                ->whereKey($payment->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $fresh->status->canTransitionTo(PaymentStatus::Pending)) {
                throw InvalidStatusTransitionException::between(
                    $fresh->status->value,
                    PaymentStatus::Pending->value,
                );
            }

            $fresh->forceFill([
                'status' => PaymentStatus::Pending,
                'rejection_reason' => $data->reason,
            ])->save();

            $this->audit->record(
                $admin,
                AdminAction::PaymentRejected,
                (int) $fresh->getKey(),
                $data->reason,
                $data->ip,
            );

            return $fresh;
        });
    }
}
