<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Resources\Api\V1\PaymentResource;
use App\Models\Task;
use Illuminate\Http\Request;

final class ShowTaskPaymentController
{
    public function __invoke(Request $request, Task $task): PaymentResource
    {
        return PaymentResource::make($task->payment()->firstOrFail());
    }
}
