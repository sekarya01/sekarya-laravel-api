<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\Domain\AccountNotActiveException;
use App\Models\User;
use App\Support\TokenIssuer;
use Illuminate\Database\ConnectionInterface;
use Laravel\Sanctum\NewAccessToken;

/**
 * Tukar long_lived token jadi access token baru.
 *
 * Dipanggil dengan long_lived token sebagai Bearer. Access token LAMA dicabut,
 * jadi begitu token baru diterbitkan yang lama langsung tidak berlaku.
 *
 * Status akun diperiksa ulang di sini: akun yang di-suspend setelah login
 * tidak boleh bisa memperpanjang akses hanya karena masih memegang
 * long_lived token.
 */
final class RefreshAccessTokenAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TokenIssuer $tokens,
    ) {}

    public function handle(User $user): NewAccessToken
    {
        return $this->db->transaction(function () use ($user): NewAccessToken {
            if (! $user->status->canReceiveTokens()) {
                throw AccountNotActiveException::becauseStatus($user->status);
            }

            $user->forceFill(['last_active_at' => now()])->save();

            return $this->tokens->rotateAccess($user);
        });
    }
}
