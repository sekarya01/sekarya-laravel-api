<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\ChangePasswordData;
use App\Exceptions\Domain\InvalidCurrentPasswordException;
use App\Models\User;
use App\Support\TokenIssuer;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Hash;

/**
 * Ganti kata sandi saat sudah login (G4).
 *
 * Sebelum ini satu-satunya jalan mengganti sandi adalah lewat tautan reset
 * email — pengguna yang masih ingat sandinya tidak punya pintu. Di sini sandi
 * lama WAJIB diperiksa: tanpa itu, siapa pun yang memegang access token yang
 * belum kedaluwarsa bisa mengunci pemiliknya dari akunnya sendiri.
 *
 * Seluruh token dicabut setelah sandi berganti, mengikuti aturan
 * `ResetPasswordAction`: token yang terbit sebelum pergantian tidak boleh
 * bertahan. Klien harus masuk ulang.
 */
final class ChangePasswordAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TokenIssuer $tokens,
    ) {}

    public function handle(ChangePasswordData $data, User $user): void
    {
        $this->db->transaction(function () use ($data, $user): void {
            if (! Hash::check($data->currentPassword, $user->password)) {
                throw InvalidCurrentPasswordException::make();
            }

            $user->forceFill([
                'password' => Hash::make($data->newPassword),
                'remember_token' => null,
            ])->save();

            $this->tokens->revokeAll($user);
        });
    }
}
