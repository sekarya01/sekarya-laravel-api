<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Payment;

use App\Actions\Admin\Payment\ConfirmPaymentAction;
use App\Http\Resources\Api\V1\Admin\AdminPaymentResource;
use App\Models\Payment;
use Illuminate\Http\Request;

/** Dana masuk → ditahan → activity dibuka. Satu-satunya jalan ke `held`. */
final class ConfirmPaymentController
{
    public function __construct(private readonly ConfirmPaymentAction $action) {}

    public function __invoke(Request $request, Payment $payment): AdminPaymentResource
    {
        $this->action->handle($payment, $request->user(), $request->ip());

        return AdminPaymentResource::make(
            $payment->refresh()->load(['task.category', 'payer']),
        );
    }
}
