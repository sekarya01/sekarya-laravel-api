<?php

declare(strict_types=1);

namespace App\Actions\Admin\Auth;

use App\Exceptions\Domain\AdminAccessDeniedException;
use App\Models\Admin;
use App\Support\TokenIssuer;
use Illuminate\Database\ConnectionInterface;
use Laravel\Sanctum\NewAccessToken;

/**
 * Tukar long_lived token pengelola jadi access token baru.
 *
 * Status diperiksa ULANG di sini. Pengelola yang dinonaktifkan setelah masuk
 * masih memegang long_lived token yang sah sampai 30 hari; tanpa pemeriksaan
 * ini ia bisa terus memperpanjang aksesnya sendiri selama sebulan.
 */
final class RefreshAdminTokenAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TokenIssuer $tokens,
    ) {}

    public function handle(Admin $admin): NewAccessToken
    {
        return $this->db->transaction(function () use ($admin): NewAccessToken {
            if (! $admin->status->isActive()) {
                throw AdminAccessDeniedException::becauseSuspended();
            }

            return $this->tokens->rotateAccess($admin);
        });
    }
}
