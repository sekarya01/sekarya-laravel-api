<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Access;

use App\Actions\Admin\Access\DeleteAdminAction;
use App\Models\Admin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class DeleteAdminController
{
    public function __construct(private readonly DeleteAdminAction $action) {}

    public function __invoke(Request $request, Admin $admin): JsonResponse
    {
        $this->action->handle($admin, $request->user(), $request->ip());

        return new JsonResponse(['message' => 'Akun pengelola dihapus.']);
    }
}
