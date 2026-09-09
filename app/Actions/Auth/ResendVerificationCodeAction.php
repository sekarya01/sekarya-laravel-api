<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\ResendCodeData;
use App\Enums\UserStatus;
use App\Models\User;

/**
 * Kirim ulang kode verifikasi.
 *
 * Selalu selesai tanpa galat meski emailnya tidak terdaftar atau akunnya
 * sudah aktif — endpoint ini tidak boleh jadi alat pengecek keberadaan akun.
 * Yang berbeda hanya: kode benar-benar dikirim atau tidak.
 */
final class ResendVerificationCodeAction
{
    public function __construct(private readonly IssueVerificationCodeAction $issueCode) {}

    public function handle(ResendCodeData $data, ?string $ip = null): void
    {
        $user = User::query()->where('email', $data->email)->first();

        if (! $user instanceof User) {
            return;
        }

        if ($user->status !== UserStatus::PendingVerification) {
            return;
        }

        // Cooldown DITEGAKKAN di sini; galatnya sengaja dibiarkan naik karena
        // pemanggilnya memang pemilik email yang sedang menunggu kode.
        $this->issueCode->handle($user, $ip);
    }
}
