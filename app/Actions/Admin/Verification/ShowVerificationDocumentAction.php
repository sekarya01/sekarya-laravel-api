<?php

declare(strict_types=1);

namespace App\Actions\Admin\Verification;

use App\Enums\AdminAction;
use App\Models\Admin;
use App\Models\UserVerification;
use App\Support\AdminAuditRecorder;
use App\Support\VerificationDocuments;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;

/**
 * Membuka BERKAS foto KTP/selfie pengajuan (G2b), dan mencatat pembacaannya.
 *
 * Sebelum ini hanya `has_id_card_photo` (boolean) yang ada; berkasnya tidak
 * punya jalur keluar sama sekali, sehingga penilaian identitas bertumpu pada
 * teks yang diketik pemiliknya. Di sini berkasnya dibaca dari disk privat —
 * tidak pernah lewat URL publik — dan setiap pembacaan meninggalkan jejak
 * audit, sama seperti `ViewVerificationAction`.
 */
final class ShowVerificationDocumentAction
{
    public function __construct(
        private readonly AdminAuditRecorder $audit,
        private readonly FilesystemFactory $filesystem,
    ) {}

    /**
     * @param  string  $kind  `id_card` atau `selfie` (dijaga route `whereIn`).
     * @return string|null Path di disk privat, atau null bila tidak ada.
     */
    public function handle(UserVerification $verification, Admin $admin, string $kind, ?string $ip = null): ?string
    {
        $path = $kind === 'selfie'
            ? $verification->selfie_photo_path
            : $verification->id_card_photo_path;

        if ($path === null || ! $this->filesystem->disk(VerificationDocuments::DISK)->exists($path)) {
            return null;
        }

        $this->audit->record(
            $admin,
            AdminAction::VerificationDocumentViewed,
            (int) $verification->getKey(),
            $kind,
            $ip,
        );

        return $path;
    }
}
