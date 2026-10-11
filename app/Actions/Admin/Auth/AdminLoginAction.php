<?php

declare(strict_types=1);

namespace App\Actions\Admin\Auth;

use App\Data\Admin\AdminLoginData;
use App\Models\Admin;
use App\Support\TokenIssuer;
use Illuminate\Database\ConnectionInterface;
use Laravel\Sanctum\NewAccessToken;

/**
 * Login pengelola. Endpoint sendiri, tabel sendiri, guard sendiri.
 *
 * Bukan `POST /auth/login` yang juga mencari di `admins`: satu endpoint yang
 * mencari di dua tabel harus memutuskan tabel mana yang menang saat satu
 * alamat ada di keduanya, dan jawaban apa pun yang dipilih akan salah pada
 * suatu hari. Terpisah, pertanyaannya tidak pernah muncul.
 */
final class AdminLoginAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TokenIssuer $tokens,
        private readonly VerifyAdminCredentialsAction $credentials,
    ) {}

    /** @return array{admin: Admin, access: NewAccessToken, long_lived: NewAccessToken} */
    public function handle(AdminLoginData $data): array
    {
        return $this->db->transaction(function () use ($data): array {
            $admin = $this->credentials->handle($data);
            $admin->recordLogin($data->ip);

            return ['admin' => $admin, ...$this->tokens->issuePair($admin)];
        });
    }
}
