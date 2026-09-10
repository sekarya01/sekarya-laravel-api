<?php

declare(strict_types=1);

namespace App\Actions\Admin\Verification;

use App\Enums\AdminAction;
use App\Models\Admin;
use App\Models\UserVerification;
use App\Support\AdminAuditRecorder;

/**
 * Membuka satu pengajuan verifikasi, DAN mencatat pembacaannya.
 *
 * Ada sebagai Action — bukan sebagai controller yang memanggil `->load()` —
 * justru karena efek sampingnya: detail inilah satu-satunya tempat NIK dan
 * nomor rekening keluar terbaca, dan pencatatan pembacaannya adalah aturan
 * bisnis, bukan penyajian.
 *
 * Jejaknya ditulis walaupun ini permintaan GET. Sebuah GET yang menulis
 * memang tidak biasa, dan di sini itu memang tujuannya: tanpa baris ini,
 * "siapa pernah membuka NIK siapa" tidak terjawab oleh apa pun.
 */
final class ViewVerificationAction
{
    public function __construct(private readonly AdminAuditRecorder $audit) {}

    public function handle(UserVerification $verification, Admin $admin, ?string $ip = null): UserVerification
    {
        $this->audit->record(
            $admin,
            AdminAction::VerificationViewed,
            (int) $verification->getKey(),
            null,
            $ip,
        );

        return $verification->load(['user', 'reviewer']);
    }
}
