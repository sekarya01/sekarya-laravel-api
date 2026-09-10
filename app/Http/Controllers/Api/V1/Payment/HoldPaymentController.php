<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payment;

use App\Actions\Payment\ReportTransferAction;
use App\Http\Resources\Api\V1\PaymentResource;
use App\Models\Task;
use Illuminate\Http\Request;

/**
 * Pemberi kerja menyatakan sudah transfer.
 *
 * NAMA RUTENYA TETAP `payment/hold`, tapi endpoint ini TIDAK LAGI MENAHAN DANA.
 * Path-nya sengaja dipertahankan supaya klien yang sudah menulis kodenya tidak
 * perlu diubah; yang berubah adalah apa yang terjadi sesudahnya.
 *
 * Dulu satu panggilan ini memindahkan tagihan langsung ke `held` — status yang
 * membuka pekerjaan — atas pernyataan pemberi kerja sendiri. Sekarang ia hanya
 * memindahkan tagihan ke `awaiting_confirmation` dan menaruhnya di antrean
 * pengelola. Yang menahan dana `POST /admin/payments/{payment}/confirm`, dan
 * itu satu-satunya jalan menuju `held`.
 *
 * Karena itu Action-nya bernama ReportTransferAction, bukan HoldPaymentAction:
 * nama kelas domain harus menyebut apa yang benar-benar dikerjakannya.
 */
final class HoldPaymentController
{
    public function __construct(private readonly ReportTransferAction $action) {}

    public function __invoke(Request $request, Task $task): PaymentResource
    {
        return PaymentResource::make(
            $this->action->handle($task, $request->user()),
        );
    }
}
