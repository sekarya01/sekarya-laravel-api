<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Auth;

use App\Actions\Auth\LogoutAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LogoutController
{
    public function __construct(private readonly LogoutAction $action) {}

    public function __invoke(Request $request): JsonResponse
    {
        $this->action->handle($request->user());

        return new JsonResponse(['message' => 'Berhasil keluar. Semua token dicabut.']);
    }
}
