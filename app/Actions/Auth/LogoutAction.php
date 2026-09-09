<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\TokenIssuer;

final class LogoutAction
{
    public function __construct(private readonly TokenIssuer $tokens) {}

    /**
     * Logout mencabut SEMUA token, termasuk long_lived.
     *
     * Mencabut access token saja akan menyisakan long_lived token yang masih
     * bisa menerbitkan akses baru — artinya "logout" tidak benar-benar
     * mengakhiri sesi.
     */
    public function handle(User $user): void
    {
        $this->tokens->revokeAll($user);
    }
}
