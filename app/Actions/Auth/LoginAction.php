<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\LoginData;
use App\Exceptions\Domain\AccountNotActiveException;
use App\Exceptions\Domain\EmailNotVerifiedException;
use App\Exceptions\Domain\InvalidCredentialsException;
use App\Models\User;
use App\Support\TokenIssuer;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;

final class LoginAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TokenIssuer $tokens,
    ) {}

    /** @return array{user: User, access: NewAccessToken, long_lived: NewAccessToken} */
    public function handle(LoginData $data): array
    {
        return $this->db->transaction(function () use ($data): array {
            $user = User::query()
                ->when($data->email !== null, fn ($q) => $q->where('email', $data->email))
                ->when($data->phone !== null, fn ($q) => $q->where('phone', $data->phone))
                ->first();

            // Hash dummy tetap diperiksa saat user tidak ada, supaya waktu
            // respons untuk "email tidak terdaftar" dan "kata sandi salah"
            // tidak berbeda dan tidak bisa dipakai menebak akun yang ada.
            $hash = $user?->password ?? '$2y$12$'.str_repeat('x', 53);

            if (! Hash::check($data->password, $hash) || ! $user instanceof User) {
                throw InvalidCredentialsException::make();
            }

            if ($user->status->isPendingVerification() || $user->email_verified_at === null) {
                throw EmailNotVerifiedException::make();
            }

            if (! $user->status->canReceiveTokens()) {
                throw AccountNotActiveException::becauseStatus($user->status);
            }

            $user->forceFill(['last_active_at' => now()])->save();

            return ['user' => $user, ...$this->tokens->issuePair($user)];
        });
    }
}
