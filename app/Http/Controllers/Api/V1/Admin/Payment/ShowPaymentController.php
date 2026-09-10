<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Payment;

use App\Http\Resources\Api\V1\Admin\AdminPaymentResource;
use App\Models\Payment;

final class ShowPaymentController
{
    public function __invoke(Payment $payment): AdminPaymentResource
    {
        return AdminPaymentResource::make(
            $payment->load(['task.category', 'payer']),
        );
    }
}
