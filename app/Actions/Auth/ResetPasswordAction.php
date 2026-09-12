<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\Domain\InvalidPasswordResetTokenException;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

/**
 * Tukar token reset sekali pakai dengan kata sandi baru.
 *
 * Seluruh token Sanctum milik pengguna dicabut: sesi di perangkat lain
 * (termasuk yang mungkin dipegang orang lain) mati saat sandi diganti.
 */
final class ResetPasswordAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(string $email, string $token, string $password): void
    {
        $status = Password::reset(
            ['email' => mb_strtolower(trim($email)), 'password' => $password, 'token' => $token],
            function (User $user) use ($password): void {
                $this->db->transaction(function () use ($user, $password): void {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => null,
                    ])->save();

                    $user->tokens()->delete();
                });

                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw InvalidPasswordResetTokenException::expiredOrUsed();
        }
    }

    /** Tautan masih hidup: token ada, cocok, dan belum lewat 60 menit. */
    public function tokenIsValid(string $email, string $token): bool
    {
        $user = User::query()->where('email', mb_strtolower(trim($email)))->first();

        if (! $user instanceof User) {
            return false;
        }

        return Password::broker()->tokenExists($user, $token);
    }
}
