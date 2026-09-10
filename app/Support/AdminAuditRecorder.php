<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AdminAction;
use App\Models\Admin;
use App\Models\AdminAuditLog;

/**
 * Satu-satunya tempat jejak tindakan pengelola ditulis.
 *
 * Ada supaya `subject_type` tidak pernah diketik dua ejaan untuk satu tabel
 * (ia diturunkan dari tindakannya) dan supaya pemotongan panjang kolom
 * dilakukan di satu titik.
 *
 * SOAL TRANSAKSI — dan ini kebalikan dari aturan penghitung percobaan kode
 * verifikasi, jadi mudah tertukar:
 *
 *   - Penghitung percobaan dicatat DI LUAR transaksi, karena percobaannya
 *     benar-benar terjadi walaupun permintaannya gagal.
 *   - Jejak ini dicatat DI DALAM transaksi Action-nya, karena ia menyatakan
 *     "pengelola ini menyetujui pembayaran itu". Kalau transaksinya gagal,
 *     tidak ada yang disetujui, dan jejak yang tertinggal akan menyatakan
 *     sesuatu yang tidak pernah terjadi.
 */
final class AdminAuditRecorder
{
    /** Batas kolom `reason`. Dipotong di sini, bukan diserahkan ke MySQL. */
    private const int REASON_LIMIT = 500;

    public function record(
        Admin $admin,
        AdminAction $action,
        int $subjectId,
        ?string $reason = null,
        ?string $ip = null,
    ): AdminAuditLog {
        return AdminAuditLog::query()->create([
            'admin_id' => $admin->getKey(),
            'action' => $action,
            'subject_type' => $action->subjectType(),
            'subject_id' => $subjectId,
            // Alasan dari command atau job bisa lebih panjang dari kolomnya.
            // MySQL dalam mode strict akan menolak seluruh INSERT-nya, jadi
            // seluruh tindakan gagal karena alasannya kepanjangan.
            'reason' => $this->trim($reason, self::REASON_LIMIT),
            'ip' => $this->trim($ip, 45),
        ]);
    }

    private function trim(?string $value, int $limit): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === null || $value === '' ? null : mb_substr($value, 0, $limit);
    }
}
