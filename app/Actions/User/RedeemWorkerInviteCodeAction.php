<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Enums\UserActiveMode;
use App\Exceptions\Domain\WorkerInviteCodeException;
use App\Models\User;
use App\Models\WorkerInviteCode;
use App\Models\WorkerInviteRedemption;
use Illuminate\Database\ConnectionInterface;

/**
 * Tukar kode undangan → akun menjadi mitra pekerja.
 *
 * Urutan kunci di dalam transaksi: kunci BARIS kodenya dulu
 * (`lockForUpdate`), baru nilai `isUsable()`. Tanpa kunci, dua redeem
 * bersamaan sama-sama membaca `used_count = 9/10` dan keduanya lolos —
 * kuota 10 dipakai 11 kali. Kunci membuat yang kedua menunggu dan membaca
 * angka yang sudah ditambah.
 *
 * Efek sukses: baris `user_workers` dibuat (kalau belum ada), `used_count`
 * +1, jejak redeem ditulis, dan `users.active_mode = working` supaya akun
 * langsung dikenali sebagai mitra (lihat `User::readyToWork()`).
 */
final class RedeemWorkerInviteCodeAction
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /**
     * @return array{code: WorkerInviteCode, already_worker: bool}
     */
    public function handle(string $plainCode, User $user): array
    {
        $hash = WorkerInviteCode::hash($plainCode);

        return $this->db->transaction(function () use ($hash, $user): array {
            /** @var WorkerInviteCode|null $code */
            $code = WorkerInviteCode::query()
                ->where('code_hash', $hash)
                ->lockForUpdate()
                ->first();

            if ($code === null) {
                throw WorkerInviteCodeException::invalid();
            }
            if (! $code->is_active) {
                throw WorkerInviteCodeException::inactive();
            }
            if ($code->isDateExpired()) {
                throw WorkerInviteCodeException::expired();
            }
            if ($code->isExhausted()) {
                throw WorkerInviteCodeException::exhausted();
            }
            if (WorkerInviteRedemption::query()
                ->where('invite_code_id', $code->id)
                ->where('user_id', $user->id)
                ->exists()) {
                throw WorkerInviteCodeException::alreadyRedeemed();
            }

            // Idempoten sebagian: akun yang SUDAH mitra tidak menghabiskan kuota.
            if ($user->active_mode === UserActiveMode::Working && $user->workerProfile !== null) {
                throw WorkerInviteCodeException::alreadyWorker();
            }

            $user->workerProfileOrCreate();
            $user->forceFill(['active_mode' => UserActiveMode::Working])->save();

            WorkerInviteRedemption::create([
                'invite_code_id' => $code->id,
                'user_id' => $user->id,
            ]);
            $code->increment('used_count');

            return ['code' => $code->refresh(), 'already_worker' => false];
        });
    }
}
