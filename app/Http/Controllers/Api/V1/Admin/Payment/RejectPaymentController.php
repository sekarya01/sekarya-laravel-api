<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Payment;

use App\Actions\Admin\Payment\RejectPaymentAction;
use App\Data\Admin\RejectPaymentData;
use App\Http\Requests\Api\V1\Admin\RejectPaymentRequest;
use App\Http\Resources\Api\V1\Admin\AdminPaymentResource;
use App\Models\Payment;

final class RejectPaymentController
{
    public function __construct(private readonly RejectPaymentAction $action) {}

    public function __invoke(RejectPaymentRequest $request, Payment $payment): AdminPaymentResource
    {
        return AdminPaymentResource::make(
            $this->action
                ->handle($payment, $request->user(), RejectPaymentData::fromRequest($request))
                ->load(['task.category', 'payer']),
        );
    }
}
