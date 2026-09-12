<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\ForgotPasswordData;
use App\Exceptions\Domain\EmailNotRegisteredException;
use App\Exceptions\Domain\ResendTooSoonException;
use App\Models\User;
use Illuminate\Support\Facades\Password;

/**
 * Kirim tautan reset kata sandi sekali pakai ke email.
 *
 * Tautan kedaluwarsa oleh DUA kondisi: waktu (60 menit, standar broker
 * `auth.passwords.users.expire`) dan pemakaian (token dihapus saat reset
 * berhasil, jadi tidak bisa dipakai dua kali).
 */
final class RequestPasswordResetLinkAction
{
    public function handle(ForgotPasswordData $data): void
    {
        $user = User::query()->where('email', $data->email)->first();

        if (! $user instanceof User) {
            throw EmailNotRegisteredException::make();
        }

        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_THROTTLED) {
            throw ResendTooSoonException::retryAfter(
                (int) config('auth.passwords.users.throttle', 60),
            );
        }
    }
}
