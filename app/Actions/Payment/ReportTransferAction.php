<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentStatus;
use App\Enums\TaskStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Payment;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;

/**
 * Pemberi kerja menyatakan sudah mengirim transfer.
 *
 * Menggantikan separuh HoldPaymentAction yang lama. Yang lama memindahkan
 * pembayaran langsung ke `held` — status yang MEMBUKA ACTIVITY — atas
 * pernyataan pemberi kerja sendiri. Sebagai stub gateway itu memadai; sebagai
 * alur transfer manual itu berarti pekerjaan bisa dimulai tanpa uang yang
 * benar-benar diterima, dan pekerja yang menanggung akibatnya.
 *
 * Action ini karena itu tidak membuka apa pun. Ia hanya menaruh tagihan di
 * antrean pengelola.
 */
final class ReportTransferAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(Task $task, User $payer): Payment
    {
        return $this->db->transaction(function () use ($task): Payment {
            // Ditolak di depan kalau perekrutan belum selesai, dengan status
            // task apa adanya. Tanpa penjaga ini kegagalannya baru muncul saat
            // pengelola mengonfirmasi — di layar orang lain, berjam-jam
            // kemudian, sebagai "tidak bisa berpindah dari open ke active".
            if ($task->status->acceptsBids()) {
                throw InvalidStatusTransitionException::between(
                    $task->status->value,
                    TaskStatus::Active->value,
                );
            }

            $payment = Payment::query()
                ->where('task_id', $task->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $payment->status->canTransitionTo(PaymentStatus::AwaitingConfirmation)) {
                throw InvalidStatusTransitionException::between(
                    $payment->status->value,
                    PaymentStatus::AwaitingConfirmation->value,
                );
            }

            $payment->forceFill([
                'status' => PaymentStatus::AwaitingConfirmation,
                'reported_at' => now(),
                // Laporan baru mengosongkan alasan penolakan yang lama.
                // Kalau tidak, pemberi kerja yang sudah memperbaiki transfernya
                // tetap melihat alasan penolakan lama seolah masih berlaku.
                'rejection_reason' => null,
            ])->save();

            return $payment;
        });
    }
}
