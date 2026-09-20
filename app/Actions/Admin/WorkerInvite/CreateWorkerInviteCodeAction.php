<?php

declare(strict_types=1);

namespace App\Actions\Admin\WorkerInvite;

use App\Models\Admin;
use App\Models\WorkerInviteCode;
use App\Support\WorkerInviteCodeGenerator;
use Carbon\CarbonInterface;

/**
 * Terbitkan satu kode undangan baru. Plain-nya hanya keluar SEKALI di respons
 * ini — tidak tersimpan di mana pun, jadi admin wajib mencatatnya saat itu juga.
 */
final class CreateWorkerInviteCodeAction
{
    /**
     * @return array{code: WorkerInviteCode, plain: string}
     */
    public function handle(
        int $maxUses,
        ?CarbonInterface $expiresAt,
        ?string $note,
        ?Admin $admin,
    ): array {
        // Tabrakan sha256 praktis mustahil, tapi unique index tetap dijaga:
        // coba ulang maksimal 5x kalau hash sudah ada.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $plain = WorkerInviteCodeGenerator::generate();
            $hash = WorkerInviteCode::hash($plain);
            if (! WorkerInviteCode::query()->where('code_hash', $hash)->exists()) {
                $code = WorkerInviteCode::create([
                    'code_hash' => $hash,
                    'prefix' => mb_substr($plain, 0, 2),
                    'max_uses' => $maxUses,
                    'expires_at' => $expiresAt,
                    'note' => $note,
                    'created_by_admin_id' => $admin?->id,
                ]);

                return ['code' => $code, 'plain' => $plain];
            }
        }

        throw new \RuntimeException('Gagal menerbitkan kode unik setelah 5 percobaan.');
    }
}
