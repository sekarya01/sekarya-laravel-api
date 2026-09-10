<?php

declare(strict_types=1);

namespace App\Actions\Admin\Auth;

use App\Models\Admin;
use App\Support\TokenIssuer;

final class AdminLogoutAction
{
    public function __construct(private readonly TokenIssuer $tokens) {}

    /** Mencabut SEMUA token, termasuk long_lived — sama seperti pengguna. */
    public function handle(Admin $admin): void
    {
        $this->tokens->revokeAll($admin);
    }
}
