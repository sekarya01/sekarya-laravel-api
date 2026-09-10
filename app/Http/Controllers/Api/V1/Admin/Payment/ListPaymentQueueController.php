<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Payment;

use App\Actions\Admin\Payment\ListPaymentQueueAction;
use App\Data\Admin\PaymentQueueData;
use App\Http\Requests\Api\V1\Admin\PaymentQueueRequest;
use App\Http\Resources\Api\V1\Admin\AdminPaymentResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ListPaymentQueueController
{
    public function __construct(private readonly ListPaymentQueueAction $action) {}

    public function __invoke(PaymentQueueRequest $request): AnonymousResourceCollection
    {
        return AdminPaymentResource::collection(
            $this->action->handle(PaymentQueueData::fromRequest($request)),
        );
    }
}
