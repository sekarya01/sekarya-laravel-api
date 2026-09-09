<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\Domain\ResendTooSoonException;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\VerificationCodeNotification;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Hash;

/**
 * Terbitkan kode verifikasi dan kirim ke email.
 *
 * Dipakai saat pendaftaran maupun saat pengguna meminta kirim ulang, supaya
 * aturan cooldown dan pembatalan kode lama hanya ada di satu tempat.
 */
final class IssueVerificationCodeAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function handle(User $user, ?string $ip = null, bool $enforceCooldown = true): EmailVerificationCode
    {
        return $this->db->transaction(function () use ($user, $ip, $enforceCooldown): EmailVerificationCode {
            $existing = EmailVerificationCode::query()
                ->where('user_id', $user->getKey())
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($enforceCooldown && $existing !== null) {
                $wait = $existing->secondsUntilResendAllowed();

                if ($wait > 0) {
                    throw ResendTooSoonException::retryAfter($wait);
                }
            }

            // Kode lama dibatalkan: kalau dibiarkan, dua kode berlaku sekaligus
            // dan jumlah percobaan yang bisa dipakai penyerang jadi berganda.
            EmailVerificationCode::query()
                ->where('user_id', $user->getKey())
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now(), 'updated_at' => now()]);

            $code = $this->generateCode();

            $record = EmailVerificationCode::query()->create([
                'user_id' => $user->getKey(),
                // Hash, bukan kodenya. Lihat catatan di migrasi.
                'code_hash' => Hash::make($code),
                'attempts' => 0,
                'expires_at' => now()->addMinutes((int) config('sekarya.verification.ttl_minutes')),
                'last_sent_at' => now(),
                'request_ip' => $ip,
            ]);

            $user->notify(new VerificationCodeNotification(
                $code,
                (int) config('sekarya.verification.ttl_minutes'),
            ));

            return $record;
        });
    }

    /**
     * Kode numerik acak yang aman secara kriptografis.
     *
     * random_int(), bukan rand()/mt_rand(): dua yang terakhir dapat diprediksi
     * dan ini adalah kredensial.
     */
    private function generateCode(): string
    {
        $length = (int) config('sekarya.verification.code_length');
        $digits = '';

        for ($i = 0; $i < $length; $i++) {
            $digits .= (string) random_int(0, 9);
        }

        return $digits;
    }
}
