<?php

declare(strict_types=1);

namespace App\Actions\Admin\Verification;

use App\Data\Admin\ReviewVerificationData;
use App\Enums\VerificationStatus;
use App\Exceptions\Domain\InvalidStatusTransitionException;
use App\Models\Admin;
use App\Models\UserVerification;
use App\Support\AdminAuditRecorder;
use Illuminate\Database\ConnectionInterface;

/**
 * Keputusan pengelola atas satu pengajuan verifikasi: setujui, tolak, cabut.
 *
 * SATU Action untuk tiga keputusan, berbeda dari Approve/RejectActivityAction
 * yang memang terpisah. Alasannya: di sana kedua keputusan mengerjakan hal
 * yang berbeda (yang satu melepas dana dan menutup task, yang lain membuka
 * sengketa). Di sini ketiganya mengerjakan hal yang sama persis — memindahkan
 * status, mencatat penilainya, menuliskan jejak — dan hanya nilai tujuannya
 * yang berbeda. Dipisah menjadi tiga kelas, aturan transisinya harus ditulis
 * tiga kali, dan satu di antaranya akan tertinggal saat aturannya berubah.
 *
 * Yang menjaga agar `/approve` tidak bisa dipakai menolak adalah DTO-nya:
 * keputusannya datang dari konstruktor bernama, bukan dari payload.
 */
final class ReviewVerificationAction
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly AdminAuditRecorder $audit,
    ) {}

    public function handle(
        UserVerification $verification,
        Admin $admin,
        ReviewVerificationData $data,
    ): UserVerification {
        return $this->db->transaction(function () use ($verification, $admin, $data): UserVerification {
            // Row lock sungguhan di InnoDB. Dua pengelola yang membuka antrean
            // yang sama lalu menekan tombol pada saat yang sama akan menilai
            // baris ini dua kali; yang kedua harus melihat status hasil yang
            // pertama, bukan status yang dibacanya sebelum menekan.
            $fresh = UserVerification::query()
                ->whereKey($verification->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $fresh->status->canTransitionTo($data->decision)) {
                throw InvalidStatusTransitionException::between(
                    $fresh->status->value,
                    $data->decision->value,
                );
            }

            $now = now();

            $fresh->forceFill([
                'status' => $data->decision,
                'reviewed_at' => $now,
                'reviewed_by' => $admin->getKey(),

                // Alasan ditulis ke kolom yang sesuai keputusannya, dan
                // dikosongkan pada keputusan lain: alasan penolakan yang
                // tertinggal pada baris yang akhirnya disetujui akan terbaca
                // pengguna sebagai penolakan yang tidak pernah dicabut.
                'rejection_reason' => $data->decision === VerificationStatus::Rejected
                    ? $data->reason
                    : null,
                'revoked_at' => $data->decision === VerificationStatus::Revoked ? $now : null,
                'revoked_reason' => $data->decision === VerificationStatus::Revoked
                    ? $data->reason
                    : null,
            ])->save();

            $this->audit->record(
                $admin,
                $data->action,
                (int) $fresh->getKey(),
                $data->reason,
                $data->ip,
            );

            return $fresh;
        });
    }
}
