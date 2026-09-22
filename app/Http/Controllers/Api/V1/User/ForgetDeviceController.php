<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Actions\User\ForgetDeviceTokenAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ForgetDeviceController
{
    public function __construct(private readonly ForgetDeviceTokenAction $action) {}

    public function __invoke(Request $request, string $token): Response
    {
        $this->action->handle($token, $request->user());

        return response()->noContent();
    }
}
