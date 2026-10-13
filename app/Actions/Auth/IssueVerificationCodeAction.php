<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Exceptions\Domain\ResendTooSoonException;
use App\Models\EmailVerificationCode;
use App\Models\User;
use App\Notifications\VerificationCodeNotification;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Throwable;

/**
 * Terbitkan kode verifikasi dan kirim ke email.
 *
 * Dipakai saat pendaftaran maupun saat pengguna meminta kirim ulang, supaya
 * aturan cooldown dan pembatalan kode lama hanya ada di satu tempat.
 */
final class IssueVerificationCodeAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * [#impure] [#io: db, mail] [#tx: own] [#lock: email_verification_codes] [#ssot]
     * [#warn: email terkirim sesudah commit transaksi TERLUAR pemanggil]
     */
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

            // SESUDAH commit (termasuk transaksi pendaftaran di luar ini): SMTP
            // yang lambat tidak boleh menahan kunci baris selama round-trip,
            // dan SMTP yang gagal tidak boleh me-rollback akun yang sudah sah.
            // Galatnya dilaporkan (log/Axiom) — pengguna sudah berada di layar
            // verifikasi dan bisa memakai "kirim ulang kode".
            $minutes = (int) config('sekarya.verification.ttl_minutes');
            DB::afterCommit(static function () use ($user, $code, $minutes): void {
                try {
                    $user->notify(new VerificationCodeNotification($code, $minutes));
                } catch (Throwable $e) {
                    report($e);
                }
            });

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
