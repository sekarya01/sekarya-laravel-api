<?php

declare(strict_types=1);

namespace App\Actions\Admin\Auth;

use App\Data\Admin\AdminLoginData;
use App\Exceptions\Domain\AdminAccessDeniedException;
use App\Exceptions\Domain\InvalidCredentialsException;
use App\Models\Admin;
use App\Support\TokenIssuer;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Hash;
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
    ) {}

    /** @return array{admin: Admin, access: NewAccessToken, long_lived: NewAccessToken} */
    public function handle(AdminLoginData $data): array
    {
        return $this->db->transaction(function () use ($data): array {
            $admin = Admin::query()->where('email', $data->email)->first();

            // Hash dummy tetap diperiksa saat akunnya tidak ada, supaya waktu
            // respons untuk "alamat tidak terdaftar" dan "sandi salah" tidak
            // berbeda dan tidak bisa dipakai menemukan alamat pengelola.
            $hash = $admin?->password ?? '$2y$12$'.str_repeat('x', 53);

            if (! Hash::check($data->password, $hash) || ! $admin instanceof Admin) {
                throw InvalidCredentialsException::make();
            }

            // Diperiksa SETELAH sandinya benar. Diperiksa sebelum itu, jawaban
            // "akun dinonaktifkan" akan membocorkan alamat mana yang ada
            // kepada orang yang belum tahu sandinya.
            if (! $admin->status->isActive()) {
                throw AdminAccessDeniedException::becauseSuspended();
            }

            $admin->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $data->ip,
            ])->save();

            return ['admin' => $admin, ...$this->tokens->issuePair($admin)];
        });
    }
}
