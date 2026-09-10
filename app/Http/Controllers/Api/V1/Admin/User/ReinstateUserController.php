<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\User;

use App\Actions\Admin\User\ChangeUserStatusAction;
use App\Data\Admin\ChangeUserStatusData;
use App\Http\Resources\Api\V1\Admin\AdminUserResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Pulihkan akun.
 *
 * Tujuannya BELUM TENTU `active`: akun yang belum pernah memverifikasi email
 * kembali ke `pending_verification`. Yang memutuskan ChangeUserStatusAction.
 */
final class ReinstateUserController
{
    public function __construct(private readonly ChangeUserStatusAction $action) {}

    public function __invoke(Request $request, User $user): AdminUserResource
    {
        return AdminUserResource::make(
            $this->action->handle($user, $request->user(), ChangeUserStatusData::reinstate($request)),
        );
    }
}
