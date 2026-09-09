<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\VerifyEmailData;
use App\Enums\UserStatus;
use App\Exceptions\Domain\InvalidCredentialsException;
use App\Exceptions\Domain\InvalidVerificationCodeException;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Support\TokenIssuer;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\NewAccessToken;

/**
 * Verifikasi kode email, aktifkan akun, terbitkan pasangan token.
 *
 * Ini satu-satunya jalan sebuah akun berpindah dari PendingVerification ke
 * Active.
 *
 * PENTING soal pembagian transaksi di bawah:
 *
 * Pencatatan percobaan gagal HARUS di luar transaksi. Versi sebelumnya
 * memanggil increment() lalu melempar galat di dalam satu transaksi — dan
 * rollback membatalkan kenaikan itu, sehingga batas percobaan per-kode tidak
 * berfungsi sama sekali. Yang tersisa hanya rate limit berbasis IP, yang bisa
 * dihindari dengan rotasi IP; kode enam angka pun jadi bisa ditebak habis.
 *
 * Jadi: gagal dicatat di luar transaksi, keberhasilan dikerjakan di dalam
 * transaksi (dengan row lock) supaya satu kode tidak bisa dipakai dua kali.
 */
final class VerifyEmailAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TokenIssuer $tokens,
    ) {}

    /** @return array{user: User, access: NewAccessToken, long_lived: NewAccessToken} */
    public function handle(VerifyEmailData $data): array
    {
        $user = User::query()->where('email', $data->email)->first();

        if (! $user instanceof User) {
            // Pesan sama dengan kredensial salah: jangan bocorkan email mana
            // yang terdaftar.
            throw InvalidCredentialsException::make();
        }

        if ($user->status === UserStatus::Active && $user->email_verified_at !== null) {
            throw InvalidVerificationCodeException::expiredOrUsed();
        }

        $record = EmailVerificationCode::query()
            ->where('user_id', $user->getKey())
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        if (! $record instanceof EmailVerificationCode || $record->isExpired()) {
            throw InvalidVerificationCodeException::expiredOrUsed();
        }

        if ($record->attemptsExhausted()) {
            throw InvalidVerificationCodeException::attemptsExhausted();
        }

        if (! Hash::check($data->code, $record->code_hash)) {
            // Di luar transaksi, jadi kenaikan ini BERTAHAN.
            $record->increment('attempts');

            $left = max(0, (int) config('sekarya.verification.max_attempts') - $record->attempts);

            throw $left === 0
                ? InvalidVerificationCodeException::attemptsExhausted()
                : InvalidVerificationCodeException::wrong($left);
        }

        return $this->consume($user, $record);
    }

    /**
     * Jalur berhasil: konsumsi kode, aktifkan akun, terbitkan token —
     * semuanya atomik, dengan row lock supaya dua permintaan bersamaan tidak
     * bisa memakai satu kode dua kali.
     *
     * @return array{user: User, access: NewAccessToken, long_lived: NewAccessToken}
     */
    private function consume(User $user, EmailVerificationCode $record): array
    {
        return $this->db->transaction(function () use ($user, $record): array {
            $locked = EmailVerificationCode::query()
                ->whereKey($record->getKey())
                ->lockForUpdate()
                ->first();

            // Diperiksa ULANG setelah lock: permintaan lain bisa saja sudah
            // memakainya di antara pemeriksaan di atas dan lock ini.
            if (! $locked instanceof EmailVerificationCode || ! $locked->isUsable()) {
                throw InvalidVerificationCodeException::expiredOrUsed();
            }

            $locked->forceFill(['consumed_at' => now()])->save();

            $user->forceFill([
                'email_verified_at' => now(),
                'status' => UserStatus::Active,
            ])->save();

            return ['user' => $user, ...$this->tokens->issuePair($user)];
        });
    }
}
