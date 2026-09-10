<?php

declare(strict_types=1);

namespace App\Actions\Admin\User;

use App\Data\Admin\ChangeUserStatusData;
use App\Enums\AdminAction;
use App\Enums\UserStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Admin;
use App\Models\User;
use App\Support\AdminAuditRecorder;
use App\Support\TokenIssuer;
use Illuminate\Database\ConnectionInterface;

/**
 * Moderasi akun pengguna: suspend, ban, pulihkan.
 *
 * SATU Action untuk tiga endpoint. Ketiganya mengerjakan hal yang sama —
 * memindahkan `users.status`, mencabut token, menuliskan jejak — dan hanya
 * status tujuannya berbeda.
 *
 * Dua hal yang tidak boleh hilang dari sini:
 *
 *  - **Token dicabut.** Status saja tidak menghentikan siapa pun: access
 *    token hidup delapan jam dan tidak menyimpan status di dalamnya, jadi
 *    akun yang di-ban tanpa pencabutan token tetap bisa menawar dan
 *    mengerjakan task sampai tokennya kedaluwarsa. Long_lived-nya bahkan
 *    30 hari. `revokeAll` mencabut keduanya.
 *  - **Pemulihan tidak selalu ke `active`.** Akun yang belum pernah
 *    memverifikasi email dikembalikan ke `pending_verification`. Kalau
 *    dikembalikan ke `active`, moderasi menjadi jalan melewati verifikasi
 *    email: suspend lalu pulihkan, dan akunnya aktif tanpa pernah memasukkan
 *    kode.
 */
final class ChangeUserStatusAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TokenIssuer $tokens,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function handle(User $user, Admin $admin, ChangeUserStatusData $data): User
    {
        return $this->db->transaction(function () use ($user, $admin, $data): User {
            $target = $this->target($user, $data->action);

            if (! $user->status->canBeMovedByAdminTo($target)) {
                throw InvalidStatusTransitionException::between(
                    $user->status->value,
                    $target->value,
                );
            }

            // forceFill: `status` sengaja tidak fillable. Lewat fill() biasa
            // nilainya akan hilang TANPA galat dan tindakan moderasi ini
            // seolah berhasil tanpa mengubah apa pun.
            $user->forceFill(['status' => $target])->save();

            if (! $target->canReceiveTokens()) {
                $this->tokens->revokeAll($user);
            }

            $this->audit->record(
                $admin,
                $data->action,
                (int) $user->getKey(),
                $data->reason,
                $data->ip,
            );

            return $user->refresh();
        });
    }

    private function target(User $user, AdminAction $action): UserStatus
    {
        return match ($action) {
            AdminAction::UserSuspended => UserStatus::Suspended,
            AdminAction::UserBanned => UserStatus::Banned,
            AdminAction::UserReinstated => $user->email_verified_at === null
                ? UserStatus::PendingVerification
                : UserStatus::Active,
            // Tidak bisa terjadi: ChangeUserStatusData hanya bisa dibangun
            // lewat tiga konstruktor bernama di atas. Disebut eksplisit
            // supaya penambahan tindakan moderasi baru gagal DI SINI, saat
            // dijalankan pertama kali, bukan diam-diam memilih cabang salah.
            default => throw new \LogicException(
                'Tindakan moderasi tanpa status tujuan: '.$action->value,
            ),
        };
    }
}
