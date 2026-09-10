<?php

declare(strict_types=1);

namespace App\Data\Admin;

use App\Data\CursorPageData;
use App\Enums\VerificationStatus;
use App\Enums\VerificationType;
use App\Http\Requests\Api\V1\Admin\VerificationQueueRequest;

/**
 * Penyaring antrean verifikasi.
 *
 * `status` kosong TIDAK berarti "semua": ia berarti "yang masih menunggu
 * keputusan". Antrean kerja yang secara bawaan menampilkan seluruh riwayat
 * akan tenggelam oleh baris yang sudah selesai dinilai, dan halaman
 * pertamanya justru berisi yang paling tidak perlu disentuh.
 */
final readonly class VerificationQueueData
{
    public function __construct(
        public CursorPageData $page,
        public ?VerificationStatus $status = null,
        public ?VerificationType $type = null,
    ) {}

    public static function fromRequest(VerificationQueueRequest $request): self
    {
        return new self(
            page: $request->page(),
            status: $request->filled('status')
                ? VerificationStatus::from($request->string('status')->value())
                : null,
            type: $request->filled('type')
                ? VerificationType::from($request->string('type')->value())
                : null,
        );
    }
}
