<?php

declare(strict_types=1);

namespace App\Actions\Admin\WorkerInvite;

use App\Data\Admin\CreateWorkerInviteCodeData;
use App\Enums\AdminAction;
use App\Models\Admin;
use App\Models\WorkerInviteCode;
use App\Support\AdminAuditRecorder;
use App\Support\WorkerInviteCodeGenerator;

/**
 * Terbitkan satu kode undangan baru.
 *
 * Plain-nya ikut TERSIMPAN (`code_plain`) supaya pengelola bisa melihatnya
 * terus-menerus di menu Kode Mitra — lihat catatan keputusannya di migrasi
 * `2026_09_21_000002`. Respons tetap mengembalikannya juga agar pembuatnya
 * langsung bisa menyalin tanpa membuka detail.
 */
final class CreateWorkerInviteCodeAction
{
    public function __construct(private readonly AdminAuditRecorder $audit) {}

    /**
     * @return array{code: WorkerInviteCode, plain: string}
     */
    public function handle(CreateWorkerInviteCodeData $data, ?Admin $admin): array
    {
        // Tabrakan sha256 praktis mustahil, tapi unique index tetap dijaga:
        // coba ulang maksimal 5x kalau hash sudah ada.
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $plain = WorkerInviteCodeGenerator::generate();
            $hash = WorkerInviteCode::hash($plain);
            if (! WorkerInviteCode::query()->where('code_hash', $hash)->exists()) {
                $code = WorkerInviteCode::create([
                    'code_hash' => $hash,
                    'code_plain' => $plain,
                    'prefix' => mb_substr($plain, 0, 2),
                    'max_uses' => $data->maxUses,
                    'expires_at' => $data->expiresAt,
                    'note' => $data->note,
                    'created_by_admin_id' => $admin?->getKey(),
                    'city' => $data->city,
                    'province' => $data->province,
                ]);

                // Jejak "siapa menerbitkan kode berkuota ini" — penting saat
                // kode bocor ke publik: tanpa ini tak ada yang bisa menjawab
                // siapa yang membuatnya. Plain-nya TIDAK dicatat di jejak
                // (cukup tersimpan di baris kodenya).
                if ($admin !== null) {
                    $this->audit->record(
                        $admin,
                        AdminAction::WorkerInviteCreated,
                        (int) $code->getKey(),
                        "maks {$data->maxUses}x"
                            .($data->expiresAt !== null ? ", kedaluwarsa {$data->expiresAt->toDateTimeString()}" : ', tanpa tanggal kedaluwarsa')
                            .($data->note !== null ? ", {$data->note}" : ''),
                        $data->ip,
                    );
                }

                return ['code' => $code, 'plain' => $plain];
            }
        }

        throw new \RuntimeException('Gagal menerbitkan kode unik setelah 5 percobaan.');
    }
}
