<?php

declare(strict_types=1);

namespace App\Actions\Admin\WorkerInvite;

use App\Enums\AdminAction;
use App\Models\Admin;
use App\Models\WorkerInviteCode;
use App\Support\AdminAuditRecorder;
use Illuminate\Database\ConnectionInterface;

/**
 * Matikan kode undangan sebelum waktunya — pintu darurat kalau kode bocor
 * atau disalahgunakan. Menonaktifkan TIDAK menghapus jejak redeem yang sudah
 * terjadi; pekerja yang sudah masuk lewat kode ini tetap mitra.
 *
 * Idempoten: mematikan kode yang sudah mati tetap sukses (bukan galat),
 * supaya tombolnya aman ditekan dua kali.
 */
final class DeactivateWorkerInviteCodeAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function handle(WorkerInviteCode $code, Admin $admin, ?string $ip = null): WorkerInviteCode
    {
        return $this->db->transaction(function () use ($code, $admin, $ip): WorkerInviteCode {
            $fresh = WorkerInviteCode::query()
                ->whereKey($code->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($fresh->is_active) {
                $fresh->forceFill(['is_active' => false])->save();

                $this->audit->record(
                    $admin,
                    AdminAction::WorkerInviteDeactivated,
                    (int) $fresh->getKey(),
                    "dimatikan saat terpakai {$fresh->used_count}/{$fresh->max_uses}",
                    $ip,
                );
            }

            return $fresh->refresh();
        });
    }
}
