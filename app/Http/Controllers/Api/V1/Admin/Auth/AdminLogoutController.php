<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Auth;

use App\Actions\Admin\Auth\AdminLogoutAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AdminLogoutController
{
    public function __construct(private readonly AdminLogoutAction $action) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->action->handle($request->user());

        return new JsonResponse(['message' => 'Sesi pengelola diakhiri.']);
    }
}
